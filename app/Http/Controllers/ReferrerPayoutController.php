<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Referrer;
use App\Models\ReferrerFloatReconciliation;
use App\Models\ReferrerPayout;
use App\Services\TransactionService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReferrerPayoutController extends Controller
{
    public function create(Referrer $referrer)
    {
        $this->authorize('view', $referrer);

        $unpaidLoans = $referrer->loans()
            ->where('status', 'paid')
            ->whereNull('referrer_payout_id')
            ->where('referrer_deducted_before_deposit', false)
            ->orderBy('repaid_date')
            ->get();

        // Per-loan cut, not a flat rate — each loan may carry its own
        // referrer_share_percentage (it can be overridden per-loan at
        // creation time in LoanGivenController@store), so the payout must
        // respect what was actually promised on each individual loan.
        $unpaidLoans = $unpaidLoans->map(function ($loan) use ($referrer) {
            $sharePct = $loan->referrer_share_percentage ?? $referrer->default_share_percentage;
            $loan->computed_share_percentage = $sharePct;
            $loan->computed_cut = round($loan->interest_amount * ($sharePct / 100), 2);
            return $loan;
        });

        $totalInterest = $unpaidLoans->sum('interest_amount');
        $totalCut = $unpaidLoans->sum('computed_cut');

        $accounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $floatAccount = $referrer->floatAccount;

        return view('referrer-payouts.create', compact(
            'referrer', 'unpaidLoans', 'totalInterest', 'totalCut', 'accounts', 'floatAccount'
        ));
    }

    public function store(Request $request, Referrer $referrer)
    {
        $this->authorize('view', $referrer);

        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'account_id' => 'required|exists:accounts,id',
            'paid_date' => 'required|date',
        ]);

        $account = Account::where('user_id', Auth::id())->findOrFail($validated['account_id']);

        $loans = $referrer->loans()
            ->where('status', 'paid')
            ->whereNull('referrer_payout_id')
            ->where('referrer_deducted_before_deposit', false)
            ->whereBetween('repaid_date', [$validated['period_start'], $validated['period_end']])
            ->get();

        if ($loans->isEmpty()) {
            return back()->with('error', 'No unpaid referred loans found in that period.');
        }

        $totalInterest = $loans->sum('interest_amount');
        // Sum each loan's own cut rather than applying one blended rate —
        // see create() for why this must be per-loan.
        $amountPaid = $loans->sum(function ($loan) use ($referrer) {
            $sharePct = $loan->referrer_share_percentage ?? $referrer->default_share_percentage;
            return round($loan->interest_amount * ($sharePct / 100), 2);
        });

        // Stored on the payout record as a derived, informational figure —
        // "what this batch worked out to, blended" — not used in any
        // calculation. Guards div-by-zero if every matched loan somehow
        // has zero interest.
        $effectiveSharePercentage = $totalInterest > 0
            ? round(($amountPaid / $totalInterest) * 100, 2)
            : 0;

        if ($amountPaid <= 0) {
            return back()->with('error', 'Computed payout amount is zero — nothing to pay.');
        }

        $fee = in_array($account->type, ['mpesa', 'airtel_money'])
            ? app(TransactionService::class)->mpesaSendMoneyFee($amountPaid)
            : 0;

        if (round((float) $account->current_balance, 2) < round($amountPaid + $fee, 2)) {
            return back()->with('error', "Insufficient balance in {$account->name}: need KES "
                . number_format($amountPaid + $fee, 2) . " (payout {$amountPaid} + fee {$fee}).");
        }
        // Paying out of HER OWN float account: the commission leaves money she's
// holding for us, so the same amount must be marked settled in float
// reconciliation, or the pending figure would still count her cut as owed
// back to us.
        $paidFromFloat = $referrer->floatAccount && $referrer->floatAccount->id === $account->id;
        $pendingByLoan = $paidFromFloat ? $referrer->pendingFloatInterestByLoan() : collect();

        DB::beginTransaction();

        try {
            $commissionCategory = Category::where('user_id', Auth::id())
                ->where('name', 'Referrer Commission')
                ->first() ?: Category::create([
                'user_id' => Auth::id(), 'parent_id' => null,
                'name' => 'Referrer Commission', 'type' => 'expense', 'is_active' => true,
            ]);

            $transaction = app(TransactionService::class)->createTransaction([
                'account_id'        => $account->id,
                'category_id'       => $commissionCategory->id,
                'amount'            => $amountPaid,
                'date'              => $validated['paid_date'],
                'description'       => "Referrer commission to {$referrer->name} ({$validated['period_start']} to {$validated['period_end']})",
                'mobile_money_type' => 'send_money', // M-Pesa send-money rates
            ]);

            $payout = ReferrerPayout::create([
                'user_id' => Auth::id(),
                'referrer_id' => $referrer->id,
                'period_start' => $validated['period_start'],
                'period_end' => $validated['period_end'],
                'total_interest' => $totalInterest,
                'share_percentage' => $effectiveSharePercentage,
                'amount_paid' => $amountPaid,
                'account_id' => $account->id,
                'transaction_id' => $transaction->id,
                'paid_date' => $validated['paid_date'],
            ]);

            // $loans()->each(fn () => null); // n/a — kept for clarity, see line below
            $loans->each(function ($loan) use ($payout) {
                $loan->referrer_payout_id = $payout->id;
                $loan->save();
            });
            $settledInFloat = 0;

            if ($paidFromFloat) {
                foreach ($loans as $loan) {
                    $sharePct = $loan->referrer_share_percentage ?? $referrer->default_share_percentage;
                    $cut = round($loan->interest_amount * ($sharePct / 100), 2);

                    // Never settle more than is actually pending for that loan: if some of
                    // the interest was routed elsewhere on closing, only the part that
                    // really sat in the float can be reconciled here.
                    $settle = min($cut, (float) ($pendingByLoan[$loan->id] ?? 0));

                    if ($settle > 0.01) {
                        ReferrerFloatReconciliation::create([
                            'user_id' => Auth::id(),
                            'loan_given_id' => $loan->id,
                            'transfer_id' => null,
                            'referrer_payout_id' => $payout->id,
                            'amount' => $settle,
                        ]);
                        $settledInFloat += $settle;
                    }
                }
            }

            DB::commit();
            $account->updateBalance();

            $msg = "Paid KES " . number_format($amountPaid, 0) . " to {$referrer->name} for {$loans->count()} loan(s).";
            if ($paidFromFloat) {
                $msg .= " Taken from her float; KES " . number_format($settledInFloat, 0)
                    . " marked as settled in float reconciliation.";
            }

            return redirect()->route('referrers.show', $referrer)->with('success', $msg);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('ReferrerPayoutController@store failed', ['referrer_id' => $referrer->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'Payout failed: ' . $e->getMessage());
        }
    }
    public function show(Referrer $referrer)
    {
        $this->authorize('view', $referrer);

        $referrer->load(['payouts.account', 'payouts.loans']);

        return view('referrers.show', compact('referrer'));
    }
}
