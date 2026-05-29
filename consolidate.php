<?php
// consolidate.php — run via: php artisan tinker --execute="require 'consolidate.php';"

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

$account = Account::where('name', 'like', '%etica%')->firstOrFail();

DB::transaction(function () use ($account) {
    $may = Transaction::where('account_id', $account->id)
        ->whereHas('category', fn($q) => $q->where('name', 'Interest'))
        ->whereYear('date', 2026)
        ->whereMonth('date', 5)
        ->whereNull('deleted_at')
        ->get();

    if ($may->isEmpty()) {
        echo "No May 2026 interest records found.\n";
        return;
    }

    $total      = $may->sum('amount');
    $count      = $may->count();
    $categoryId = $may->first()->category_id;

    echo "Found {$count} daily records totalling KES {$total}\n";

    Transaction::whereIn('id', $may->pluck('id'))->delete();

    $consolidated = $account->transactions()->create([
        'user_id'        => $account->user_id,
        'amount'         => $total,
        'date'           => '2026-05-31',
        'description'    => 'Interest earned – May 2026 (consolidated)',
        'category_id'    => $categoryId,
        'payment_method' => 'Interest',
        'type'           => 'income',
    ]);

    $account->updateBalance();

    echo "Created consolidated record ID {$consolidated->id} for KES {$total} on 2026-05-31\n";
    echo "Balance updated.\n";
});
