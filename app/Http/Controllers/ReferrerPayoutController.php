<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\LoanGivenPayment;
use App\Models\Referrer;
use App\Models\ReferrerFloatReconciliation;
use App\Models\ReferrerPayout;
use App\Services\LoanBookStatsService;
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

        // Unpaid commission now lives on payments, not on closed loans.
        $unpaidPayments = LoanGivenPayment::with('loanGiven')
            ->whereNull('referrer_payout_id')
            ->where('referrer_commission_kept', false)
            ->where('referrer_commission', '>', 0)
            ->whereHas('loanGiven', fn ($q) => $q->where('referrer_id', $referrer->id))
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        // One row per loan, with that loan's unpaid interest and cut.
        // Each row is a LoanGiven with extra display attributes:
        //   unpaid_interest, computed_cut, computed_share_percentage, unpaid_payments
        $unpaidLoans = $unpaidPayments
            ->groupBy('loan_given_id')
            ->map(function ($group) {
                $loan = $group->first()->loanGiven;
                $loan->unpaid_payments           = $group;
                $loan->unpaid_interest           = round((float) $group->sum('interest_portion'), 2);
                $loan->computed_cut              = round((float) $group->sum('referrer_commission'), 2);
                $loan->computed_share_percentage = $group->first()->referrer_share_percentage;
                return $loan;
            })
            ->sortBy(fn ($l) => $l->unpaid_payments->min('payment_date'))
            ->values();

        $totalInterest = round((float) $unpaidPayments->sum('interest_portion'), 2);
        $totalCut      = round((float) $unpaidPayments->sum('referrer_commission'), 2);
        $recoveryOwed      = $referrer->recoveryOutstanding();
        $recoveryDeduction = round(min($totalCut, $recoveryOwed), 2);
        $netPayable        = round($totalCut - $recoveryDeduction, 2);

        // Empty accounts are never offered.
        $accounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn ($a) => (float) $a->current_balance > 0)
            ->values();

        $floatAccount = $referrer->floatAccount;

        // The form narrows the account list to those that can cover the chosen period.
        $payoutData = [
            'payments' => $unpaidPayments->map(fn ($p) => [
                'date' => $p->payment_date->format('Y-m-d'),
                'cut'  => (float) $p->referrer_commission,
            ])->values(),
            'accounts' => $accounts->map(fn ($a) => [
                'id'      => (string) $a->id,
                'name'    => $a->name,
                'balance' => (float) $a->current_balance,
                'charges' => $this->chargesPayoutFee($a),
                'isFloat' => $floatAccount && $floatAccount->id === $a->id,
            ])->values(),
            'fees'         => (object) $this->payoutFeesByAmount($unpaidPayments, $recoveryOwed),
            'recoveryOwed' => $recoveryOwed,
            'start'        => (string) old('period_start', ''),
            'end'          => (string) old('period_end', now()->format('Y-m-d')),
            'accountId'    => (string) old('account_id', ''),
        ];

        return view('referrer-payouts.create', compact(
            'referrer', 'unpaidLoans', 'unpaidPayments', 'totalInterest', 'totalCut', 'accounts', 'floatAccount','recoveryOwed', 'recoveryDeduction', 'netPayable', 'payoutData'
        ));
    }

    private function chargesPayoutFee(Account $account): bool
    {
        return in_array($account->type, ['mpesa', 'airtel_money']);
    }

    /**
     * Send-money fee for every net amount a date range could produce,
     * keyed by the amount in cents.
     */
    private function payoutFeesByAmount($payments, float $recoveryOwed): array
    {
        $daily = $payments
            ->groupBy(fn ($p) => $p->payment_date->format('Y-m-d'))
            ->sortKeys()
            ->map(fn ($g) => (float) $g->sum('referrer_commission'))
            ->values();

        $fees = [];

        foreach ($daily->keys() as $from) {
            $gross = 0.0;

            foreach ($daily->slice($from) as $amount) {
                $gross = round($gross + $amount, 2);
                $net   = round($gross - min($gross, $recoveryOwed), 2);

                if ($net > 0) {
                    $fees[(int) round($net * 100)] = (float) app(TransactionService::class)->mpesaSendMoneyFee($net);
                }
            }
        }

        return $fees;
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

        // Unpaid, not-kept commission on payments dated within the period.
        $payments = LoanGivenPayment::with('loanGiven')
            ->whereNull('referrer_payout_id')
            ->where('referrer_commission_kept', false)
            ->where('referrer_commission', '>', 0)
            ->whereBetween('payment_date', [$validated['period_start'], $validated['period_end']])
            ->whereHas('loanGiven', fn ($q) => $q->where('referrer_id', $referrer->id))
            ->get();

        if ($payments->isEmpty()) {
            return back()->with('error', 'No unpaid commission found in that period.');
        }

        $totalInterest   = round((float) $payments->sum('interest_portion'), 2);
        $grossCommission = round((float) $payments->sum('referrer_commission'), 2);

        if ($grossCommission <= 0) {
            return back()->with('error', 'Computed payout amount is zero — nothing to pay.');
        }

// Write-off recoveries come off the top, oldest first; any remainder carries forward.
        $recoveryOwed      = $referrer->recoveryOutstanding();
        $recoveryDeduction = round(min($grossCommission, $recoveryOwed), 2);
        $amountPaid        = round($grossCommission - $recoveryDeduction, 2); // actual cash out

        $effectiveSharePercentage = $totalInterest > 0
            ? round(($grossCommission / $totalInterest) * 100, 2) : 0;

        $fee = ($amountPaid > 0 && $this->chargesPayoutFee($account))
            ? app(TransactionService::class)->mpesaSendMoneyFee($amountPaid) : 0;

        if ($amountPaid > 0 && round((float) $account->current_balance, 2) < round($amountPaid + $fee, 2)) {
            return back()->with('error', "Insufficient balance in {$account->name}: need "
                . number_format($amountPaid + $fee, 2) . " (payout {$amountPaid} + fee {$fee}).");
        }

        $paidFromFloat = $referrer->floatAccount && $referrer->floatAccount->id === $account->id;
        $pendingByLoan = $paidFromFloat ? $referrer->pendingFloatInterestByLoan() : collect();

        DB::beginTransaction();

        try {
            $transaction = null;

            if ($amountPaid > 0) {
                $commissionCategory = Category::where('user_id', Auth::id())
                    ->where('name', 'Referrer Commission')->first()
                    ?: Category::create([
                        'user_id' => Auth::id(), 'parent_id' => null,
                        'name' => 'Referrer Commission', 'type' => 'expense', 'is_active' => true,
                    ]);

                $transaction = app(TransactionService::class)->createTransaction([
                    'account_id'        => $account->id,
                    'category_id'       => $commissionCategory->id,
                    'amount'            => $amountPaid,
                    'date'              => $validated['paid_date'],
                    'description'       => "Referrer commission to {$referrer->name} ({$validated['period_start']} to {$validated['period_end']})"
                        . ($recoveryDeduction > 0 ? " — net of {$recoveryDeduction} write-off recovery" : ''),
                    'mobile_money_type' => 'send_money',
                ]);
            }

            $payout = ReferrerPayout::create([
                'user_id'           => Auth::id(),
                'referrer_id'       => $referrer->id,
                'period_start'      => $validated['period_start'],
                'period_end'        => $validated['period_end'],
                'total_interest'    => $totalInterest,
                'share_percentage'  => $effectiveSharePercentage,
                'gross_commission'  => $grossCommission,
                'recovery_deducted' => $recoveryDeduction,
                'amount_paid'       => $amountPaid,
                'account_id'        => $account->id,
                'transaction_id'    => $transaction?->id,
                'paid_date'         => $validated['paid_date'],
            ]);

            if ($recoveryDeduction > 0) {
                $referrer->applyRecoveries($payout, $recoveryDeduction);
            }

            LoanGivenPayment::whereIn('id', $payments->pluck('id'))
                ->update(['referrer_payout_id' => $payout->id]);

            $settledInFloat = 0;

            // Only the cash that actually left her float is "settled" there. The deducted
            // part stays in the float and becomes yours to reconcile.
            if ($paidFromFloat && $amountPaid > 0) {
                $scale = $amountPaid / $grossCommission;

                foreach ($payments->groupBy('loan_given_id') as $loanId => $group) {
                    $cut    = round((float) $group->sum('referrer_commission') * $scale, 2);
                    $settle = min($cut, (float) ($pendingByLoan[$loanId] ?? 0));

                    if ($settle > 0.01) {
                        ReferrerFloatReconciliation::create([
                            'user_id' => Auth::id(), 'loan_given_id' => $loanId,
                            'transfer_id' => null, 'referrer_payout_id' => $payout->id,
                            'amount' => $settle,
                        ]);
                        $settledInFloat += $settle;
                    }
                }
            }

            DB::commit();
            $account->updateBalance();

            $loanCount = $payments->pluck('loan_given_id')->unique()->count();

            $msg = "Commission " . number_format($grossCommission, 0) . " for {$referrer->name} "
                . "({$payments->count()} payment(s), {$loanCount} loan(s)): "
                . "" . number_format($recoveryDeduction, 0) . " applied to written-off loans, "
                . "" . number_format($amountPaid, 0) . " paid out.";

            $left = $referrer->recoveryOutstanding();
            if ($left > 0) {
                $msg .= " " . number_format($left, 0) . " still to recover from future commissions.";
            }

            if ($paidFromFloat && $amountPaid > 0) {
                $msg .= " Taken from her float; " . number_format($settledInFloat, 0)
                    . " marked as settled in float reconciliation.";
            }

            return redirect()->route('referrers.show', $referrer)->with('success', $msg);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('ReferrerPayoutController@store failed', ['referrer_id' => $referrer->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'Payout failed: ' . $e->getMessage());
        }
    }
    public function show(Request $request, Referrer $referrer, LoanBookStatsService $stats)
    {
        $this->authorize('view', $referrer);

        // Full payouts relation is still used for the "Paid Out" totals;
        // the paginated lists below are what the tables render.
        $referrer->load('payouts');

        $loansPage = $referrer->loans()
            ->with('payments')
            ->orderByDesc('disbursed_date')
            ->paginate(10, ['*'], 'loans_page')
            ->withQueryString();

        $payoutsPage = $referrer->payouts()
            ->with(['account', 'loans'])
            ->orderByDesc('paid_date')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'payouts_page')
            ->withQueryString();

        $statsPeriod = $request->input('stats_period', 'this_month');
        [$statsStart, $statsEnd, $statsLabel] = LoanBookStatsService::resolvePeriod(
            $statsPeriod, $request->input('stats_start'), $request->input('stats_end')
        );

        $loans    = $referrer->loans()->get();
        $payments = LoanGivenPayment::where('user_id', Auth::id())
            ->whereIn('loan_given_id', $loans->pluck('id'))
            ->get();

        $loanStats = $stats->compute($loans, $payments, $statsStart, $statsEnd);

        return view('referrers.show', compact(
            'referrer', 'loanStats', 'statsPeriod', 'statsStart', 'statsEnd', 'statsLabel',
            'loansPage', 'payoutsPage'
        ));
    }
}
