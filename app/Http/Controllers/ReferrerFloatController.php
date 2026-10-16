<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\LoanGiven;
use App\Models\Referrer;
use App\Models\ReferrerFloatReconciliation;
use App\Models\Transfer;
use App\Services\ReferrerLedgerService;
use App\Services\TransferFeeCalculator;
use App\Services\TransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReferrerFloatController extends Controller
{
    public function __construct(
        protected TransferService $transferService,
        protected TransferFeeCalculator $feeCalculator,
    ) {}

    // ── index: the reconciliation screen ────────────────────────────────────

    public function index(Referrer $referrer)
    {
        if ($referrer->user_id !== Auth::id()) {
            abort(403);
        }

        $floatAccount = $referrer->floatAccount;

        $pendingInterest  = $referrer->pendingFloatInterestByLoan();
        $pendingPrincipal = $referrer->pendingFloatPrincipalByLoan();

        $loanIds = $pendingInterest->keys()
            ->merge($pendingPrincipal->keys())
            ->unique()
            ->values();

        $pendingLoans = $loanIds->isEmpty()
            ? collect()
            : LoanGiven::whereIn('id', $loanIds)
                ->get()
                ->map(function ($loan) use ($pendingInterest, $pendingPrincipal) {
                    $loan->pending_float_interest  = (float) ($pendingInterest[$loan->id] ?? 0);
                    $loan->pending_float_principal = (float) ($pendingPrincipal[$loan->id] ?? 0);
                    return $loan;
                })
                ->sortByDesc(fn ($l) => $l->pending_float_interest + $l->pending_float_principal)
                ->values();

        $totals = [
            'interest'  => round((float) $pendingInterest->sum(), 2),
            'principal' => round((float) $pendingPrincipal->sum(), 2),
        ];

        $destinationAccounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->whereIn('type', ['mpesa', 'bank', 'cash'])
            ->orderBy('name')
            ->get();

        return view('referrers.float-reconcile', compact(
            'referrer', 'floatAccount', 'pendingLoans', 'destinationAccounts', 'totals'
        ));
    }

    // ── reconcile: record what she actually sent, split by loan AND by
    //    principal / interest ────────────────────────────────────────────────

    public function reconcile(Request $request, Referrer $referrer)
    {
        try {
            if ($referrer->user_id !== Auth::id()) {
                abort(403);
            }

            $floatAccount = $referrer->floatAccount;

            if (!$floatAccount) {
                return back()->with('error', "{$referrer->name} doesn't have a float account set up yet.");
            }

            $validated = $request->validate([
                'destination_account_id'  => 'required|exists:accounts,id',
                'date'                    => 'required|date',
                'description'             => 'nullable|string',
                'allocations'             => 'required|array',
                'allocations.interest.*'  => 'nullable|numeric|min:0',
                'allocations.principal.*' => 'nullable|numeric|min:0',
            ]);

            // Re-check against current pending amounts server-side — the form
            // only knows what was pending when the page loaded, and it's a
            // financial figure, so it isn't trusted blindly.
            $pending = [
                'interest'  => $referrer->pendingFloatInterestByLoan(),
                'principal' => $referrer->pendingFloatPrincipalByLoan(),
            ];

            $rows = collect();

            foreach (['interest', 'principal'] as $component) {
                foreach (($validated['allocations'][$component] ?? []) as $loanId => $amount) {
                    $amount = round((float) $amount, 2);

                    if ($amount <= 0) {
                        continue;
                    }

                    $available = (float) ($pending[$component][$loanId] ?? 0);

                    if ($amount > $available + 0.01) {
                        $name = LoanGiven::find($loanId)?->borrower_name ?? "loan #{$loanId}";

                        return back()->with('error',
                            ucfirst($component) . " for {$name} (KES " . number_format($amount, 0)
                            . ") exceeds what's pending (KES " . number_format($available, 0) . ")."
                        )->withInput();
                    }

                    $rows->push([
                        'loan_id'   => $loanId,
                        'amount'    => $amount,
                        'component' => $component,
                    ]);
                }
            }

            if ($rows->isEmpty()) {
                return back()->with('error', 'Enter an amount for at least one loan.')->withInput();
            }

            $total = round((float) $rows->sum('amount'), 2);

            $destination = Account::withoutGlobalScopes()->findOrFail($validated['destination_account_id']);

            if ($destination->user_id !== Auth::id()) {
                abort(403);
            }

            DB::beginTransaction();

            try {
                // Watermark: TransferService::execute() only returns the fee it
                // charged, not the Transfer row itself, so the transfer is
                // recovered by id immediately after — safe here because nothing
                // else can interleave a write between these two calls within a
                // single synchronous request.
                $lastIdBefore = Transfer::withoutGlobalScopes()
                    ->where('user_id', Auth::id())
                    ->max('id') ?? 0;

                $this->transferService->execute(
                    $floatAccount,
                    $destination,
                    $total,
                    $validated['date'],
                    $validated['description'] ?: "Float remittance from {$referrer->name}",
                );

                $transfer = Transfer::withoutGlobalScopes()
                    ->where('user_id', Auth::id())
                    ->where('id', '>', $lastIdBefore)
                    ->latest('id')
                    ->first();

                if (!$transfer) {
                    throw new \RuntimeException('Could not locate the transfer that was just created.');
                }

                foreach ($rows as $r) {
                    ReferrerFloatReconciliation::create([
                        'user_id'       => Auth::id(),
                        'loan_given_id' => $r['loan_id'],
                        'transfer_id'   => $transfer->id,
                        'amount'        => $r['amount'],
                        'component'     => $r['component'],
                    ]);
                }

                DB::commit();

            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            $interestTotal  = round((float) $rows->where('component', 'interest')->sum('amount'), 2);
            $principalTotal = round((float) $rows->where('component', 'principal')->sum('amount'), 2);
            $loanCount      = $rows->pluck('loan_id')->unique()->count();

            return redirect()->route('referrers.float.index', $referrer)
                ->with('success', "KES " . number_format($total, 0)
                    . " reconciled across {$loanCount} loan(s): "
                    . "KES " . number_format($interestTotal, 0) . " interest, "
                    . "KES " . number_format($principalTotal, 0) . " principal.");

        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (Throwable $e) {
            Log::error('ReferrerFloatController@reconcile failed', [
                'referrer_id' => $referrer->id ?? null,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'Failed to reconcile: ' . $e->getMessage())->withInput();
        }
    }

    // ── statement ───────────────────────────────────────────────────────────

    public function statement(Referrer $referrer, ReferrerLedgerService $ledger)
    {
        if ($referrer->user_id !== Auth::id()) {
            abort(403);
        }

        return view('referrers.statement', [
            'referrer'   => $referrer,
            'commission' => $ledger->commissionLedger($referrer),
            'float'      => $ledger->floatLedger($referrer),
            'movements'  => $ledger->allMovements($referrer),
        ]);
    }
}
