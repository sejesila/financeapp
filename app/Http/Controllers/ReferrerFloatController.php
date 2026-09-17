<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\LoanGiven;
use App\Models\Referrer;
use App\Models\ReferrerFloatReconciliation;
use App\Models\Transfer;
use App\Services\TransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReferrerFloatController extends Controller
{
    public function __construct(protected TransferService $transferService)
    {
    }

    // ── index: the reconciliation screen ────────────────────────────────────

    public function index(Referrer $referrer)
    {
        if ($referrer->user_id !== Auth::id()) {
            abort(403);
        }

        $floatAccount = $referrer->floatAccount;
        $pendingByLoan = $referrer->pendingFloatInterestByLoan();

        $pendingLoans = $pendingByLoan->isEmpty()
            ? collect()
            : LoanGiven::whereIn('id', $pendingByLoan->keys())
                ->get()
                ->map(function ($loan) use ($pendingByLoan) {
                    $loan->pending_float_interest = $pendingByLoan[$loan->id];
                    return $loan;
                })
                ->sortByDesc('pending_float_interest')
                ->values();

        $destinationAccounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->whereIn('type', ['mpesa', 'bank', 'cash'])
            ->orderBy('name')
            ->get();

        return view('referrers.float-reconcile', compact(
            'referrer', 'floatAccount', 'pendingLoans', 'destinationAccounts'
        ));
    }

    // ── reconcile: record what she actually sent, split across loans ───────

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
                'date' => 'required|date',
                'description' => 'nullable|string',
                'allocations' => 'required|array',
                'allocations.*' => 'nullable|numeric|min:0',
            ]);

            $allocations = collect($validated['allocations'])
                ->filter(fn ($amount) => (float) $amount > 0)
                ->map(fn ($amount) => round((float) $amount, 2));

            if ($allocations->isEmpty()) {
                return back()->with('error', 'Enter an amount for at least one loan.')->withInput();
            }

            // Re-check against current pending amounts server-side — the form
            // only knows what was pending when the page loaded, and it's a
            // financial figure, so it isn't trusted blindly.
            $pendingByLoan = $referrer->pendingFloatInterestByLoan();

            foreach ($allocations as $loanId => $amount) {
                $pending = (float) ($pendingByLoan[$loanId] ?? 0);
                if ($amount > $pending + 0.01) {
                    $loan = LoanGiven::find($loanId);
                    return back()->with('error',
                        "Amount for " . ($loan?->borrower_name ?? "loan #{$loanId}") . " (KES "
                        . number_format($amount, 0) . ") exceeds what's actually pending for that loan "
                        . "(KES " . number_format($pending, 0) . ")."
                    )->withInput();
                }
            }

            $total = $allocations->sum();
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
                    $validated['description'] ?: "Interest remittance from {$referrer->name}",
                );

                $transfer = Transfer::withoutGlobalScopes()
                    ->where('user_id', Auth::id())
                    ->where('id', '>', $lastIdBefore)
                    ->latest('id')
                    ->first();

                if (!$transfer) {
                    throw new \RuntimeException('Could not locate the transfer that was just created.');
                }

                foreach ($allocations as $loanId => $amount) {
                    ReferrerFloatReconciliation::create([
                        'user_id' => Auth::id(),
                        'loan_given_id' => $loanId,
                        'transfer_id' => $transfer->id,
                        'amount' => $amount,
                    ]);
                }

                DB::commit();

            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            return redirect()->route('referrers.float.index', $referrer)
                ->with('success', "KES " . number_format($total, 0)
                    . " reconciled across " . $allocations->count() . " loan(s).");

        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (Throwable $e) {
            Log::error('ReferrerFloatController@reconcile failed', [
                'referrer_id' => $referrer->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'Failed to reconcile: ' . $e->getMessage())->withInput();
        }
    }
}
