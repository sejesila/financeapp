<?php

namespace App\Services;

use App\Models\ClientFund;
use App\Models\ClientFundTransaction;
use App\Models\Transaction;
use App\Models\Transfer;

class BorrowedFundReturnService
{
    /**
     * Call this after any Transaction or Transfer that represents money
     * genuinely landing back in a specific account — a top-up, the
     * destination side of a transfer, or an SMS-detected deposit.
     *
     * Scoped to $accountId: only ClientFunds held in *that* account are
     * matched, since borrowing is recorded against the account it was
     * actually withdrawn from (see TransferService::getOutstandingClientFunds()).
     * A deposit into a different account should never repay a shortfall
     * sitting in Etica.
     *
     * Do NOT call this from:
     *  - ClientFundController::store()        (new client money in)
     *  - ClientFundController::recordProfit()  (your income)
     *  - interest-recording                     (your income)
     *  - any path that already set is_client_fund on this deposit
     *    (that's new client money, not a repayment — check the flag
     *    before calling)
     *
     * FIFO, oldest ClientFund.received_date first within the account.
     * Applies at most $depositAmount; leftover is untouched (genuinely
     * new balance). Safe/no-op if nothing is owed in that account.
     *
     * @return float amount actually applied (may be 0)
     */
    public function applyDepositAgainstBorrowed(
        int $userId,
        int $accountId,
        float $depositAmount,
        string $date,
        ?Transaction $depositTransaction = null,
        ?Transfer $transfer = null,
        ?string $description = null
    ): float {
        if ($depositAmount <= 0) {
            return 0.0;
        }

        $fundsWithUnreturned = ClientFund::where('user_id', $userId)
            ->where('account_id', $accountId)
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('received_date')
            ->get()
            ->map(function ($fund) {
                $borrowed = $fund->transactions()->where('is_borrowed', true)->sum('amount');
                $returned = $fund->transactions()->where('type', 'return')->sum('amount');
                return ['fund' => $fund, 'unreturned' => max(0, $borrowed - $returned)];
            })
            ->filter(fn($row) => $row['unreturned'] > 0)
            ->values();

        if ($fundsWithUnreturned->isEmpty()) {
            return 0.0;
        }

        $remaining = $depositAmount;
        $applied = 0.0;

        foreach ($fundsWithUnreturned as $row) {
            if ($remaining <= 0) break;

            $fund = $row['fund'];
            $portion = min($remaining, $row['unreturned']);
            if ($portion <= 0) continue;

            $fund->amount_spent -= $portion;
            $fund->updateBalance();

            ClientFundTransaction::create([
                'client_fund_id' => $fund->id,
                'transaction_id' => $depositTransaction?->id,
                'transfer_id'    => $transfer?->id,
                'type'           => 'return',
                'is_borrowed'    => false,
                'amount'         => $portion,
                'date'           => $date,
                'description'    => $description
                    ?: "Auto-matched deposit — applied against {$fund->client_name}'s unreturned borrowed balance",
            ]);

            $remaining -= $portion;
            $applied += $portion;
        }

        return $applied;
    }
}
