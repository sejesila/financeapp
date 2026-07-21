<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Services\FriendLoanReconciliationService;

class TransactionObserver
{
    public function created(Transaction $transaction): void
    {
        $transaction->account->updateBalance();

        if ($transaction->category?->name === 'Loan Recovery') {
            app(FriendLoanReconciliationService::class)->reconcile($transaction);
        }
    }

    public function updated(Transaction $transaction): void
    {
        $transaction->account->updateBalance();
    }

    public function deleted(Transaction $transaction): void
    {
      //  \Log::info('deleted() fired', ['id' => $transaction->id, 'category' => $transaction->category?->name]);

        // Cascade: a transaction's linked fee no longer represents a real
        // cost once the transaction itself is gone, so remove it too.
        // feeTransaction() is scoped withoutGlobalScope('ownedByUser') on
        // the model, and the fee itself never has its own
        // related_fee_transaction_id, so this can't recurse.
        if ($transaction->related_fee_transaction_id) {
            $transaction->feeTransaction?->delete();
        }

        $transaction->account->updateBalance();
    }

    public function forceDeleted(Transaction $transaction): void
    {
        if ($transaction->related_fee_transaction_id) {
            $transaction->feeTransaction?->forceDelete();
        }

        $transaction->account?->updateBalance();
    }
}
