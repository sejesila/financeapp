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
        \Log::info('deleted() fired', ['id' => $transaction->id, 'category' => $transaction->category?->name]);
        $transaction->account->updateBalance();
    }

    public function forceDeleted(Transaction $transaction): void
    {
        $transaction->account?->updateBalance();
    }
}
