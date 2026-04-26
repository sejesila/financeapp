<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeService(): TransactionService
{
    return app(TransactionService::class);
}

function expenseCategory(User $user, string $name = 'Food'): Category
{
    return Category::factory()->create([
        'user_id' => $user->id,
        'type'    => 'expense',
        'name'    => $name,
    ]);
}

function incomeCategory(User $user, string $name = 'Salary'): Category
{
    return Category::factory()->create([
        'user_id' => $user->id,
        'type'    => 'income',
        'name'    => $name,
    ]);
}

// ─── M-Pesa Fee Tiers (send_money) ───────────────────────────────────────────

dataset('mpesa_send_money_fees', [
    'free tier (50 KES)'    => [50,    'send_money', 0],
    'low tier (101 KES)'    => [101,   'send_money', 7],
    'mid tier (1000 KES)'   => [1000,  'send_money', 13],
    'high tier (5000 KES)'  => [5000,  'send_money', 57],
    'max tier (50001 KES)'  => [50001, 'send_money', 112],
]);

it('calculates correct M-Pesa send_money fee', function (int $amount, string $type, int $expectedFee) {
    $user     = User::factory()->create();
    Auth::login($user);

    $account  = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 999999]);
    $category = expenseCategory($user);

    $transaction = makeService()->createTransaction([
        'user_id'           => $user->id,
        'date'              => now()->toDateString(),
        'description'       => 'Test',
        'amount'            => $amount,
        'category_id'       => $category->id,
        'account_id'        => $account->id,
        'mobile_money_type' => $type,
    ]);

    $feeAmount = $transaction->feeTransaction?->amount ?? 0;
    expect((float) $feeAmount)->toBe((float) $expectedFee);
})->with('mpesa_send_money_fees');

// ─── M-Pesa PayBill Fees ──────────────────────────────────────────────────────

it('charges zero M-Pesa paybill fee for amounts under 100', function () {
    $user     = User::factory()->create();
    Auth::login($user);

    $account  = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 999999]);
    $category = expenseCategory($user);

    $transaction = makeService()->createTransaction([
        'user_id'           => $user->id,
        'date'              => now()->toDateString(),
        'description'       => 'Paybill test',
        'amount'            => 50,
        'category_id'       => $category->id,
        'account_id'        => $account->id,
        'mobile_money_type' => 'paybill',
    ]);

    expect($transaction->feeTransaction)->toBeNull();
});

it('charges correct M-Pesa paybill fee for 1000 KES', function () {
    $user     = User::factory()->create();
    Auth::login($user);

    $account  = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 999999]);
    $category = expenseCategory($user);

    $transaction = makeService()->createTransaction([
        'user_id'           => $user->id,
        'date'              => now()->toDateString(),
        'description'       => 'Paybill test',
        'amount'            => 1000,
        'category_id'       => $category->id,
        'account_id'        => $account->id,
        'mobile_money_type' => 'paybill',
    ]);

    expect((float) ($transaction->feeTransaction?->amount ?? 0))->toBe(10.0);
});

// ─── M-Pesa Buy Goods — always free ──────────────────────────────────────────

it('never charges a fee for M-Pesa buy_goods', function () {
    $user     = User::factory()->create();
    Auth::login($user);

    $account  = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 999999]);
    $category = expenseCategory($user);

    $transaction = makeService()->createTransaction([
        'user_id'           => $user->id,
        'date'              => now()->toDateString(),
        'description'       => 'Buy goods test',
        'amount'            => 5000,
        'category_id'       => $category->id,
        'account_id'        => $account->id,
        'mobile_money_type' => 'buy_goods',
    ]);

    expect($transaction->feeTransaction)->toBeNull();
});

// ─── Internet & Communication — no fee ───────────────────────────────────────

it('charges no M-Pesa fee for Internet and Communication category', function () {
    $user     = User::factory()->create();
    Auth::login($user);

    $account  = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 999999]);
    $category = expenseCategory($user, 'Internet and Communication');

    $transaction = makeService()->createTransaction([
        'user_id'           => $user->id,
        'date'              => now()->toDateString(),
        'description'       => 'Airtime purchase',
        'amount'            => 500,
        'category_id'       => $category->id,
        'account_id'        => $account->id,
        'mobile_money_type' => 'paybill',
    ]);

    expect($transaction->feeTransaction)->toBeNull();
});

// ─── Cash / Bank — never a fee ───────────────────────────────────────────────

it('never charges a fee for cash account transactions', function () {
    $user     = User::factory()->create();
    Auth::login($user);

    $account  = Account::factory()->create(['user_id' => $user->id, 'type' => 'cash', 'current_balance' => 999999]);
    $category = expenseCategory($user);

    $transaction = makeService()->createTransaction([
        'user_id'     => $user->id,
        'date'        => now()->toDateString(),
        'description' => 'Cash expense',
        'amount'      => 5000,
        'category_id' => $category->id,
        'account_id'  => $account->id,
    ]);

    expect($transaction->feeTransaction)->toBeNull();
});

// ─── Insufficient Balance ─────────────────────────────────────────────────────

it('throws an exception when account balance is insufficient for expense + fee', function () {
    $user     = User::factory()->create();
    Auth::login($user);

    // Balance of 100, trying to spend 100 with a send_money fee on top (50 KES = free, but
    // 100 KES is the boundary — it's in the free tier so no fee, use 101 to guarantee a fee)
    $account  = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 107]);
    $category = expenseCategory($user);

    expect(fn () => makeService()->createTransaction([
        'user_id'           => $user->id,
        'date'              => now()->toDateString(),
        'description'       => 'Overspend',
        'amount'            => 101,
        'category_id'       => $category->id,
        'account_id'        => $account->id,
        'mobile_money_type' => 'send_money',
    ]))->toThrow(\Exception::class, 'Insufficient balance');
});

// ─── Split Transactions ───────────────────────────────────────────────────────

it('creates split transactions across two accounts', function () {
    $user    = User::factory()->create();
    Auth::login($user);

    $mpesa = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 999999]);
    $cash  = Account::factory()->create(['user_id' => $user->id, 'type' => 'cash',  'current_balance' => 999999]);
    $cat   = expenseCategory($user);

    $transaction = makeService()->createTransaction([
        'user_id'     => $user->id,
        'date'        => now()->toDateString(),
        'description' => 'Split expense',
        'amount'      => 1000,
        'category_id' => $cat->id,
        'account_id'  => $mpesa->id,
        'splits'      => [
            ['account_id' => $mpesa->id, 'amount' => 600, 'mobile_money_type' => 'send_money'],
            ['account_id' => $cash->id,  'amount' => 400],
        ],
    ]);

    expect($transaction->is_split)->toBeTrue()
        ->and($transaction->splits)->toHaveCount(2);
});

it('rejects splits where amounts do not add up to total', function () {
    $user    = User::factory()->create();
    Auth::login($user);

    $mpesa = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 999999]);
    $cash  = Account::factory()->create(['user_id' => $user->id, 'type' => 'cash',  'current_balance' => 999999]);
    $cat   = expenseCategory($user);

    expect(fn () => makeService()->createTransaction([
        'user_id'     => $user->id,
        'date'        => now()->toDateString(),
        'description' => 'Bad split',
        'amount'      => 1000,
        'category_id' => $cat->id,
        'account_id'  => $mpesa->id,
        'splits'      => [
            ['account_id' => $mpesa->id, 'amount' => 400, 'mobile_money_type' => 'send_money'],
            ['account_id' => $cash->id,  'amount' => 400],
        ],
    ]))->toThrow(\Exception::class, 'Split amounts');
});

// ─── Update Transaction ───────────────────────────────────────────────────────

it('updates a transaction and recalculates fee', function () {
    $user     = User::factory()->create();
    Auth::login($user);

    $account  = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 999999]);
    $category = expenseCategory($user);

    // 500 KES send_money = 7 KES fee (101–500 tier)
    $transaction = makeService()->createTransaction([
        'user_id'           => $user->id,
        'date'              => now()->toDateString(),
        'description'       => 'Original',
        'amount'            => 500,
        'category_id'       => $category->id,
        'account_id'        => $account->id,
        'mobile_money_type' => 'send_money',
    ]);

    // Update to 1000 KES → 13 KES fee (501–1000 tier)
    $updated = makeService()->updateTransaction($transaction, [
        'date'              => now()->toDateString(),
        'description'       => 'Updated',
        'amount'            => 1000,
        'category_id'       => $category->id,
        'account_id'        => $account->id,
        'mobile_money_type' => 'send_money',
    ]);

    expect((float) ($updated->feeTransaction?->amount ?? 0))->toBe(13.0);
});

it('removes fee transaction when updated transaction no longer incurs a fee', function () {
    $user     = User::factory()->create();
    Auth::login($user);

    $account  = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 999999]);
    $category = expenseCategory($user);

    // 200 KES send_money → 7 KES fee
    $transaction = makeService()->createTransaction([
        'user_id'           => $user->id,
        'date'              => now()->toDateString(),
        'description'       => 'Original',
        'amount'            => 200,
        'category_id'       => $category->id,
        'account_id'        => $account->id,
        'mobile_money_type' => 'send_money',
    ]);

    expect($transaction->feeTransaction)->not->toBeNull();

    // Update to buy_goods → always free
    $updated = makeService()->updateTransaction($transaction, [
        'date'              => now()->toDateString(),
        'description'       => 'Updated buy goods',
        'amount'            => 200,
        'category_id'       => $category->id,
        'account_id'        => $account->id,
        'mobile_money_type' => 'buy_goods',
    ]);

    expect($updated->fresh()->feeTransaction)->toBeNull();
});

// ─── Delete Transaction ───────────────────────────────────────────────────────

it('soft-deletes a transaction and its associated fee', function () {
    $user     = User::factory()->create();
    Auth::login($user);

    $account  = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 999999]);
    $category = expenseCategory($user);

    $transaction = makeService()->createTransaction([
        'user_id'           => $user->id,
        'date'              => now()->toDateString(),
        'description'       => 'To be deleted',
        'amount'            => 200,
        'category_id'       => $category->id,
        'account_id'        => $account->id,
        'mobile_money_type' => 'send_money',
    ]);

    $feeId = $transaction->feeTransaction->id;
    makeService()->deleteTransaction($transaction);

    expect(Transaction::withoutGlobalScope('ownedByUser')->withTrashed()->find($transaction->id)->trashed())->toBeTrue()
        ->and(Transaction::withoutGlobalScope('ownedByUser')->withTrashed()->find($feeId)->trashed())->toBeTrue();
});

it('recalculates account balance after transaction deletion', function () {
    $user     = User::factory()->create();
    Auth::login($user);

    $account  = Account::factory()->create(['user_id' => $user->id, 'type' => 'cash', 'current_balance' => 5000, 'initial_balance' => 5000]);
    $category = expenseCategory($user);

    $transaction = makeService()->createTransaction([
        'user_id'     => $user->id,
        'date'        => now()->toDateString(),
        'description' => 'Expense',
        'amount'      => 1000,
        'category_id' => $category->id,
        'account_id'  => $account->id,
    ]);

    $account->refresh();
    $balanceAfterExpense = (float) $account->current_balance;

    makeService()->deleteTransaction($transaction);

    $account->refresh();
    expect((float) $account->current_balance)->toBeGreaterThan($balanceAfterExpense);
});
