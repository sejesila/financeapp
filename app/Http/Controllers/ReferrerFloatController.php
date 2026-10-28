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

    // ── index ───────────────────────────────────────────────────────────────

    public function index(Referrer $referrer)
    {
        if ($referrer->user_id !== Auth::id()) {
            abort(403);
        }

        $floatAccount = $referrer->floatAccount;
        $limits       = $referrer->floatReconcileLimits();

        // Interest stays per loan (it is never re-lent, so per-loan is meaningful).
        $interestByLoan = $referrer->floatInterestAvailableByLoan();

        $pendingLoans = $interestByLoan->isEmpty()
            ? collect()
            : LoanGiven::whereIn('id', $interestByLoan->keys())
                ->get()
                ->map(function ($loan) use ($interestByLoan) {
                    $loan->pending_float_interest = (float) $interestByLoan[$loan->id];
                    return $loan;
                })
                ->sortByDesc('pending_float_interest')
                ->values();

        $destinationAccounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->whereIn('type', ['mpesa', 'bank', 'cash'])
            ->orderBy('name')
            ->get();

        // View needs: $limits['balance'|'commission'|'interest'|'principal'|'max_total'],
        // $pendingLoans (interest boxes only) and ONE principal field: principal_amount.
        return view('referrers.float-reconcile', compact(
            'referrer', 'floatAccount', 'pendingLoans', 'destinationAccounts', 'limits'
        ));
    }

    // ── reconcile ───────────────────────────────────────────────────────────

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
                'destination_account_id' => 'required|exists:accounts,id',
                'date'                   => 'required|date',
                'description'            => 'nullable|string',
                'principal_amount'       => 'nullable|numeric|min:0',
                'allocations.interest.*' => 'nullable|numeric|min:0',
            ]);

            // Re-derive every limit server-side; the form is not trusted.
            $limits         = $referrer->floatReconcileLimits();
            $interestByLoan = $referrer->floatInterestAvailableByLoan();

            $rows = collect();

            // ── interest: per loan, capped per loan ──
            foreach (($validated['allocations']['interest'] ?? []) as $loanId => $amount) {
                $amount = round((float) $amount, 2);

                if ($amount <= 0) {
                    continue;
                }

                $available = (float) ($interestByLoan[$loanId] ?? 0);

                if ($amount > $available + 0.01) {
                    $name = LoanGiven::find($loanId)?->borrower_name ?? "loan #{$loanId}";

                    return back()->with('error',
                        "Interest for {$name} (KES " . number_format($amount, 0)
                        . ") exceeds what's yours to take (KES " . number_format($available, 0)
                        . "), after her unpaid commission."
                    )->withInput();
                }

                $rows->push(['loan_id' => $loanId, 'amount' => $amount, 'component' => 'interest']);
            }

            $interestTotal = round((float) $rows->sum('amount'), 2);

            if ($interestTotal > $limits['interest'] + 0.01) {
                return back()->with('error',
                    'Interest (KES ' . number_format($interestTotal, 0) . ') is more than the float can release (KES '
                    . number_format($limits['interest'], 0) . ').'
                )->withInput();
            }

            // ── principal: ONE pool, capped by real cash ──
            $principalTotal = round((float) ($validated['principal_amount'] ?? 0), 2);

            if ($principalTotal > $limits['principal'] + 0.01) {
                return back()->with('error',
                    'Principal (KES ' . number_format($principalTotal, 0) . ') is more than she is actually holding (KES '
                    . number_format($limits['principal'], 0) . '). Money already lent out again is not in the float.'
                )->withInput();
            }

            $total = round($interestTotal + $principalTotal, 2);

            if ($total <= 0) {
                return back()->with('error', 'Enter an amount to reconcile.')->withInput();
            }

            if ($total > $limits['max_total'] + 0.01) {
                return back()->with('error',
                    'Total (KES ' . number_format($total, 0) . ') is more than the most you can reconcile now (KES '
                    . number_format($limits['max_total'], 0) . ' = float balance KES '
                    . number_format($limits['balance'], 0) . ' minus her unpaid commission KES '
                    . number_format($limits['commission'], 0) . ').'
                )->withInput();
            }

            // Principal rows still need a loan id for the existing schema, so the
            // pool is spread oldest-first over loans that have principal on record.
            if ($principalTotal > 0) {
                $left = $principalTotal;

                foreach ($referrer->pendingFloatPrincipalByLoan() as $loanId => $pending) {
                    if ($left <= 0.005) {
                        break;
                    }

                    $take = round(min($left, (float) $pending), 2);

                    if ($take > 0) {
                        $rows->push(['loan_id' => $loanId, 'amount' => $take, 'component' => 'principal']);
                        $left = round($left - $take, 2);
                    }
                }

                if ($left > 0.01) {
                    throw new \RuntimeException('Could not attach the principal to any loan record.');
                }
            }

            $destination = Account::withoutGlobalScopes()->findOrFail($validated['destination_account_id']);

            if ($destination->user_id !== Auth::id()) {
                abort(403);
            }

            DB::beginTransaction();

            try {
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

            return redirect()->route('referrers.float.index', $referrer)
                ->with('success', 'KES ' . number_format($total, 0) . ' reconciled: KES '
                    . number_format($interestTotal, 0) . ' interest, KES '
                    . number_format($principalTotal, 0) . ' principal.');

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
