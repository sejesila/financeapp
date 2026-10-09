<?php

namespace App\Services;

use App\Models\LoanGiven;
use App\Models\LoanGivenPayment;
use App\Models\Referrer;
use App\Models\ReferrerPayout;
use App\Models\Transaction;
use App\Models\Transfer;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds two statements for a referrer, each with credits, debits, a running
 * balance and a short title on every line:
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

        $paymentsByTxn = LoanGivenPayment::with('loanGiven')
            ->whereIn('transaction_id', $txnIds)->get()->keyBy('transaction_id');

        $paymentsById = LoanGivenPayment::with('loanGiven')
            ->whereIn('id', $txns->whereNotNull('reference_id')->pluck('reference_id'))
            ->get()->keyBy('id');

        $loansByDisbursement = LoanGiven::whereIn('disbursement_transaction_id', $txnIds)
            ->get()->keyBy('disbursement_transaction_id');

        $rows = collect();

        foreach ($txns as $t) {
            $cat = $t->category?->name;
            $effect = $this->signedEffect($t);

            $title = match (true) {
                (bool) $t->is_transaction_fee  => 'Transaction fee',
                $cat === 'Loan Recovery'       => 'Repayment: ' . ($paymentsByTxn->get($t->id)?->loanGiven?->borrower_name ?? 'borrower'),
                $cat === 'Loan Interest'       => 'Interest: ' . ($paymentsById->get($t->reference_id)?->loanGiven?->borrower_name ?? 'borrower'),
                $cat === 'Friend Loan Given'   => 'Loan given: ' . ($loansByDisbursement->get($t->id)?->borrower_name ?? 'borrower'),
                $cat === 'Referrer Commission' => "Commission paid: {$referrer->name}",
                default => $t->description ?: ($effect >= 0 ? 'Money in' : 'Money out'),
            };

            if ($effect == 0.0 && (float) $t->amount != 0.0) {
                $title .= $t->value_date && Carbon::parse($t->value_date)->isFuture()
                    ? ' (clears ' . Carbon::parse($t->value_date)->format('M j') . ')'
                    : ' (not counted)';
            }

            // Same-day order: borrower money in (0), transfers in (1), everything
            // else (2), transfers out (3). Ties go by created_at, then id.
            // A transfer's fee sits right after that transfer.
            $priority = in_array($cat, ['Loan Recovery', 'Loan Interest'], true) ? 0 : 2;

            $sort = ($t->is_transaction_fee && $t->transfer_id)
                ? [$t->date->format('Y-m-d'), 3, (int) $t->transfer_id, 1]
                : [$t->date->format('Y-m-d'), $priority, $t->created_at?->timestamp ?? 0, $t->id];

            $rows->push([
                'date'   => $t->date,
                'sort'   => $sort,
                'title'  => $title,
                'credit' => $effect > 0 ? $effect : 0,
                'debit'  => $effect < 0 ? -$effect : 0,
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

        $rows = collect();

        foreach ($transfers as $tr) {
            $date = Carbon::parse($tr->date);
            $out = (int) $tr->from_account_id === $accountId;

            // Mirrors Account::updateBalance(): incoming transfers don't count
            // until their value_date has arrived.
            $incomingPending = !$out && $tr->value_date && Carbon::parse($tr->value_date)->isFuture();

            // Any transfer fee is its own fee transaction on the account,
            // so it appears as a "Transaction fee" line, not here.
            $rows->push($out ? [
                'date'   => $date,
                'sort'   => [$date->format('Y-m-d'), 3, $tr->id, 0],
                'title'  => 'Remitted to ' . ($tr->toAccount?->name ?? 'you'),
                'credit' => 0,
                'debit'  => (float) $tr->amount,
            ] : [
                // Sorts after borrower repayments/interest but before spending.
                'date'   => $date,
                'sort'   => [$date->format('Y-m-d'), 1, $tr->id, 0],
                'title'  => 'Transfer in: ' . ($tr->fromAccount?->name ?? 'another account')
                    . ($incomingPending ? ' (clears ' . Carbon::parse($tr->value_date)->format('M j') . ')' : ''),
                'credit' => $incomingPending ? 0 : (float) $tr->amount,
                'debit'  => 0,
            ]);
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
        $pending = $t->value_date && Carbon::parse($t->value_date)->isFuture();

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

    // ── 2. COMMISSION LEDGER ────────────────────────────────────────────────
    // Credit = commission she has earned. Debit = commission paid or kept.
    // Balance = what you still owe her.

    public function commissionLedger(Referrer $referrer): array
    {
        $loans = $referrer->loans()
            ->where('status', 'paid')
            ->orderBy('repaid_date')->orderBy('id')
            ->get();

        $payouts = ReferrerPayout::whereIn('id', $loans->pluck('referrer_payout_id')->filter())
            ->get()->keyBy('id');

        $rows = collect();

        foreach ($loans as $loan) {
            $share = (float) ($loan->referrer_share_percentage ?? $referrer->default_share_percentage);
            $deducted = (bool) $loan->referrer_deducted_before_deposit;

            // If she kept her cut before depositing, her real cut is what she kept.
            $cut = $deducted
                ? (float) $loan->referrer_retained_amount
                : round((float) $loan->interest_amount * ($share / 100), 2);

            if ($cut <= 0) {
                continue;
            }

            $earnedOn = Carbon::parse($loan->repaid_date);

            $rows->push([
                'date'   => $earnedOn,
                'sort'   => [$earnedOn->format('Y-m-d'), 1, $loan->id],
                'title'  => "Earned: {$loan->borrower_name}",
                'credit' => $cut,
                'debit'  => 0,
            ]);

            if ($deducted) {
                $rows->push([
                    'date'   => $earnedOn,
                    'sort'   => [$earnedOn->format('Y-m-d'), 2, $loan->id],
                    'title'  => "Kept: {$loan->borrower_name}",
                    'credit' => 0,
                    'debit'  => $cut,
                ]);
            } elseif ($loan->referrer_payout_id && ($payout = $payouts->get($loan->referrer_payout_id))) {
                $paidOn = Carbon::parse($payout->paid_date);

                $rows->push([
                    'date'   => $paidOn,
                    'sort'   => [$paidOn->format('Y-m-d'), 2, $loan->id],
                    'title'  => "Paid: {$loan->borrower_name}",
                    'credit' => 0,
                    'debit'  => $cut,
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
