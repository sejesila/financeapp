<?php

namespace App\Services;

use App\Models\LoanGiven;
use App\Models\LoanGivenPayment;
use App\Models\Referrer;
use App\Models\ReferrerFloatReconciliation;
use App\Models\ReferrerPayout;
use App\Models\Transaction;
use App\Models\Transfer;
use Illuminate\Support\Collection;

/**
 * Builds two statements for a referrer, each with credits, debits, a running
 * balance and a plain-English description on every line:
 *
 *  1. floatLedger()      – real money moving in/out of her float account.
 *  2. commissionLedger() – her referral cut: earned vs. paid/kept vs. still owed.
 */
class ReferrerLedgerService
{
    /**
     * Set to true ONLY if TransferService::execute() also writes a Transaction
     * row on each account for a transfer (then transfers would show up twice).
     * Check with: Transaction::where('account_id', $floatId)->latest()->take(5)->get()
     * right after a remittance. If you see a row for it, set this to true.
     */
    private const TRANSFERS_ALREADY_IN_TRANSACTIONS = false;

    // ── 1. FLOAT ACCOUNT LEDGER ─────────────────────────────────────────────

    public function floatLedger(Referrer $referrer): array
    {
        $account = $referrer->floatAccount;

        if (!$account) {
            return ['account' => null, 'rows' => collect()];
        }

        $txns = Transaction::with('category')
            ->where('account_id', $account->id)
            ->orderBy('date')->orderBy('id')
            ->get();

        $txnIds = $txns->pluck('id');

        // Repayment transactions -> the loan payment that created them.
        $paymentsByTxn = LoanGivenPayment::with('loanGiven')
            ->whereIn('transaction_id', $txnIds)->get()->keyBy('transaction_id');

        // "Loan Interest" transactions point at their payment via reference_id.
        $paymentsById = LoanGivenPayment::with('loanGiven')
            ->whereIn('id', $txns->whereNotNull('reference_id')->pluck('reference_id'))
            ->get()->keyBy('id');

        // Disbursement transactions -> the loan.
        $loansByDisbursement = LoanGiven::whereIn('disbursement_transaction_id', $txnIds)
            ->get()->keyBy('disbursement_transaction_id');

        // Commission paid out of her float -> the payout record.
        $payoutsByTxn = ReferrerPayout::whereIn('transaction_id', $txnIds)
            ->get()->keyBy('transaction_id');

        $rows = collect();

        foreach ($txns as $t) {
            $cat = $t->category?->name;
            $effect = $this->signedEffect($t);
            $isCredit = $effect >= 0;

            [$title, $detail] = match (true) {
                (bool) $t->is_transaction_fee => $this->describeFee($t, $txns),

                $cat === 'Loan Recovery' => $this->describeRepayment($paymentsByTxn->get($t->id), $t),

                $cat === 'Loan Interest' => $this->describeInterest($paymentsById->get($t->reference_id), $t),

                $cat === 'Friend Loan Given' => $this->describeDisbursement($loansByDisbursement->get($t->id), $t),

                $cat === 'Referrer Commission' => $this->describeCommission($payoutsByTxn->get($t->id), $referrer, $t),

                default => [$t->description ?: ($isCredit ? 'Money in' : 'Money out'), $cat ? "Category: {$cat}" : null],
            };

            if ($effect == 0.0 && (float) $t->amount != 0.0) {
                $detail = trim(($detail ? $detail . ' ' : '')
                    . ($t->value_date && \Carbon\Carbon::parse($t->value_date)->isFuture()
                        ? '(Not in the balance yet — clears on ' . \Carbon\Carbon::parse($t->value_date)->format('M j') . '.)'
                        : '(Not counted in the account balance.)'));
            }

            $rows->push([
                'date' => $t->date,
                'sort' => [$t->date->format('Y-m-d'), 1, $t->id],
                'title' => $title,
                'detail' => $detail,
                'credit' => $effect > 0 ? $effect : 0,
                'debit' => $effect < 0 ? -$effect : 0,
            ]);
        }

        if (!self::TRANSFERS_ALREADY_IN_TRANSACTIONS) {
            $rows = $rows->merge($this->transferRows($account->id));
        }

        $rows = $rows->sortBy('sort')->values();

        // Running balance. Opening balance assumed to be the account's initial
        // balance; if your column is named differently, change it here.
        $opening = (float) ($account->initial_balance ?? 0);
        $running = $opening;

        $rows = $rows->map(function ($row) use (&$running) {
            $running += $row['credit'] - $row['debit'];
            $row['balance'] = round($running, 2);
            return $row;
        });

        $closing = round($running, 2);
        $current = (float) $account->current_balance;

        return [
            'account' => $account,
            'rows' => $rows,
            'opening' => $opening,
            'total_credits' => $rows->sum('credit'),
            'total_debits' => $rows->sum('debit'),
            'closing' => $closing,
            'current_balance' => $current,
            // Anything other than ~0 means the ledger and the stored balance disagree.
            'difference' => round($current - $closing, 2),
        ];
    }

    private function transferRows(int $accountId): Collection
    {
        $transfers = Transfer::withoutGlobalScopes()
            ->where(fn ($q) => $q->where('from_account_id', $accountId)->orWhere('to_account_id', $accountId))
            ->with(['fromAccount', 'toAccount'])
            ->orderBy('date')->orderBy('id')
            ->get();

        $coverage = ReferrerFloatReconciliation::with('loanGiven')
            ->whereIn('transfer_id', $transfers->pluck('id'))
            ->get()->groupBy('transfer_id');

        $rows = collect();

        foreach ($transfers as $tr) {
            $date = \Carbon\Carbon::parse($tr->date);
            $out = (int) $tr->from_account_id === $accountId;

            // Mirrors Account::updateBalance(): incoming transfers don't count
            // until their value_date has arrived.
            $incomingPending = !$out && $tr->value_date && \Carbon\Carbon::parse($tr->value_date)->isFuture();

            if ($out) {
                $covers = ($coverage[$tr->id] ?? collect())
                    ->map(fn ($r) => ($r->loanGiven?->borrower_name ?? 'loan #' . $r->loan_given_id)
                        . ' (KES ' . number_format($r->amount, 0) . ')')
                    ->implode(', ');

                $rows->push([
                    'date' => $date,
                    'sort' => [$date->format('Y-m-d'), 2, $tr->id],
                    'title' => 'Remitted to ' . ($tr->toAccount?->name ?? 'your account'),
                    'detail' => $covers
                        ? "She sent you this interest from the float. Covers: {$covers}."
                        : 'Transfer out of the float.',
                    'credit' => 0,
                    'debit' => (float) $tr->amount,
                ]);

                // Any transfer fee is its own fee transaction on the account,
                // so it appears as a "Transaction fee" line, not here.
            } else {
                $rows->push([
                    'date' => $date,
                    'sort' => [$date->format('Y-m-d'), 2, $tr->id],
                    'title' => 'Transfer in from ' . ($tr->fromAccount?->name ?? 'another account'),
                    'detail' => ($tr->description ?: 'Money moved into the float.')
                        . ($incomingPending ? ' (Not in the balance yet — clears on ' . \Carbon\Carbon::parse($tr->value_date)->format('M j') . '.)' : ''),
                    'credit' => $incomingPending ? 0 : (float) $tr->amount,
                    'debit' => 0,
                ]);
            }
        }

        return $rows;
    }

    /**
     * Signed effect of a transaction on the account balance, replicating the
     * category-based rules in Account::updateBalance() (not transactions.type).
     * Positive = credit, negative = debit, 0 = not counted.
     */
    private function signedEffect(Transaction $t): float
    {
        $name = $t->category?->name;
        $type = $t->category?->type;
        $amount = (float) $t->amount;
        $pending = $t->value_date && \Carbon\Carbon::parse($t->value_date)->isFuture();

        if ($name === 'Balance Adjustment') {
            return $amount; // already signed
        }

        if (in_array($name, ['Loan Fees Refund', 'Facility Fee Refund'], true)) {
            return $amount;
        }

        return match ($type) {
            'income' => ($pending && $name !== 'Interest') ? 0.0 : $amount,
            'expense' => -$amount,
            'liability' => match ($name) {
                'Loan Receipt' => $amount,
                'Client Funds' => $amount < 0 ? $amount : ($pending ? 0.0 : $amount),
                default => 0.0,
            },
            default => 0.0,
        };
    }

    private function describeRepayment(?LoanGivenPayment $p, Transaction $t): array
    {
        if (!$p) {
            return [$t->description ?: 'Loan repayment', 'Principal returned by a borrower.'];
        }

        $name = $p->loanGiven?->borrower_name ?? 'a borrower';

        return [
            "Repayment received — {$name}",
            'Money returned by the borrower into the float. Any interest earned on it is shown as its own "Interest earned" line. Loan #' . $p->loan_given_id . '.',
        ];
    }

    private function describeInterest(?LoanGivenPayment $p, Transaction $t): array
    {
        $name = $p?->loanGiven?->borrower_name ?? 'a borrower';

        return [
            "Interest earned — {$name}",
            'Your profit on this loan. It stays in her float until she remits it to you (see Reconcile Float).',
        ];
    }

    private function describeDisbursement(?LoanGiven $loan, Transaction $t): array
    {
        $name = $loan?->borrower_name ?? 'a borrower';

        return [
            "Loan given — {$name}",
            'Principal sent out to the borrower from the float' . ($loan ? ". Loan #{$loan->id}." : '.'),
        ];
    }

    private function describeFee(Transaction $t, Collection $all): array
    {
        $parent = $all->firstWhere('id', $t->fee_for_transaction_id);

        return [
            'Transaction fee',
            $parent ? 'Charged for: ' . $parent->description : 'Fee charged on a transfer or payment.',
        ];
    }

    private function describeCommission(?ReferrerPayout $payout, Referrer $referrer, Transaction $t): array
    {
        if (!$payout) {
            return ["Commission paid to {$referrer->name}", $t->description];
        }

        return [
            "Commission paid to {$referrer->name}",
            'Her referral cut for ' . \Carbon\Carbon::parse($payout->period_start)->format('M j') . ' – ' . \Carbon\Carbon::parse($payout->period_end)->format('M j, Y')
            . ' (payout #' . $payout->id . '), taken from the float. That amount is also marked as settled in float reconciliation.',
        ];
    }

    // ── 2. COMMISSION LEDGER ────────────────────────────────────────────────
    // Credit = commission she has earned. Debit = commission paid or kept.
    // Balance = what you still owe her.

    public function commissionLedger(Referrer $referrer): array
    {
        $loans = $referrer->loans()
            ->where('status', 'paid')
            ->orderBy('repaid_date')->orderBy('id')
            ->get();

        $payouts = ReferrerPayout::with('account')
            ->whereIn('id', $loans->pluck('referrer_payout_id')->filter())
            ->get()->keyBy('id');

        $rows = collect();

        foreach ($loans as $loan) {
            $share = (float) ($loan->referrer_share_percentage ?? $referrer->default_share_percentage);
            $interest = (float) $loan->interest_amount;
            $deducted = (bool) $loan->referrer_deducted_before_deposit;

            // If she kept her cut before depositing, her real cut is what she kept.
            $cut = $deducted
                ? (float) $loan->referrer_retained_amount
                : round($interest * ($share / 100), 2);

            if ($cut <= 0) {
                continue;
            }

            $earnedOn = \Carbon\Carbon::parse($loan->repaid_date);

            $rows->push([
                'date' => $earnedOn,
                'sort' => [$earnedOn->format('Y-m-d'), 1, $loan->id],
                'title' => "Commission earned — {$loan->borrower_name}",
                // When she kept her share before depositing, interest_amount is only
                // what reached you; the retained amount is grossed up
                // (interest × share ÷ (100 − share)), so her cut is share% of
                // interest + retained, not share% of interest_amount.
                'detail' => ($deducted
                        ? number_format($share, 0) . '% of the full KES ' . number_format($interest + $cut, 0)
                        . ' interest collected (KES ' . number_format($interest, 0) . ' reached you after she kept her share).'
                        : number_format($share, 0) . '% of KES ' . number_format($interest, 0) . ' interest.')
                    . ' Loan #' . $loan->id . ' closed ' . $earnedOn->format('M j, Y') . '.',
                'credit' => $cut,
                'debit' => 0,
            ]);

            if ($deducted) {
                $rows->push([
                    'date' => $earnedOn,
                    'sort' => [$earnedOn->format('Y-m-d'), 2, $loan->id],
                    'title' => "Kept by {$referrer->name} — {$loan->borrower_name}",
                    'detail' => 'She deducted her share before depositing, so nothing further is owed on this loan.',
                    'credit' => 0,
                    'debit' => $cut,
                ]);
            } elseif ($loan->referrer_payout_id && ($payout = $payouts->get($loan->referrer_payout_id))) {
                $paidOn = \Carbon\Carbon::parse($payout->paid_date);

                $rows->push([
                    'date' => $paidOn,
                    'sort' => [$paidOn->format('Y-m-d'), 2, $loan->id],
                    'title' => "Paid out — {$loan->borrower_name}",
                    'detail' => 'Included in payout #' . $payout->id . ', paid from ' . ($payout->account?->name ?? 'an account') . '.',
                    'credit' => 0,
                    'debit' => $cut,
                ]);
            }
        }

        $rows = $rows->sortBy('sort')->values();
        $running = 0.0;

        $rows = $rows->map(function ($row) use (&$running) {
            $running += $row['credit'] - $row['debit'];
            $row['balance'] = round($running, 2);
            return $row;
        });

        return [
            'rows' => $rows,
            'total_earned' => $rows->sum('credit'),
            'total_settled' => $rows->sum('debit'),
            'still_owed' => round($running, 2),
        ];
    }
}
