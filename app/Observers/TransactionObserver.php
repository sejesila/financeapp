<?php

namespace App\Observers;

use App\Models\Transaction;

class TransactionObserver
{
    public function created(Transaction $transaction): void
    {
        $transaction->account->updateBalance();
    }

    public function updated(Transaction $transaction): void
    {
        $transaction->account->updateBalance();
    }

    public function deleted(Transaction $transaction): void
    {
        $transaction->account->updateBalance();
    }

    public function forceDeleted(Transaction $transaction): void
    {
        $transaction->account->updateBalance();
    }
}
