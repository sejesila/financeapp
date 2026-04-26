<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ─── Scopes ───────────────────────────────────────────────────────────────────

it('withoutFees scope excludes fee transactions', function () {
    $user    = User::factory()->create();
    Auth::login($user);

    $account = Account::factory()->create(['user_id' => $user->id]);
    $cat     = Category::factory()->create(['user_id' => $user->id, 'type' => 'expense']);

    Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $cat->id, 'is_transaction_fee' => false, 'description' => 'Normal']);
    Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $cat->id, 'is_transaction_fee' => true,  'description' => 'Fee tx']);

    $results = Transaction::withoutFees()->get();

    expect($results->pluck('description'))->toContain('Normal')
        ->and($results->pluck('description'))->not->toContain('Fee tx');
});

it('onlyFees scope returns only fee transactions', function () {
    $user    = User::factory()->create();
    Auth::login($user);

    $account = Account::factory()->create(['user_id' => $user->id]);
    $cat     = Category::factory()->create(['user_id' => $user->id, 'type' => 'expense']);

    Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $cat->id, 'is_transaction_fee' => false, 'description' => 'Normal']);
    Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $cat->id, 'is_transaction_fee' => true,  'description' => 'Fee tx']);

    $results = Transaction::onlyFees()->get();

    expect($results->pluck('description'))->toContain('Fee tx')
        ->and($results->pluck('description'))->not->toContain('Normal');
});

// ─── Global Scope ─────────────────────────────────────────────────────────────

it('global scope limits transactions to the authenticated user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $accA = Account::factory()->create(['user_id' => $userA->id]);
    $accB = Account::factory()->create(['user_id' => $userB->id]);
    $catA = Category::factory()->create(['user_id' => $userA->id, 'type' => 'expense']);
    $catB = Category::factory()->create(['user_id' => $userB->id, 'type' => 'expense']);

    Transaction::factory()->create(['user_id' => $userA->id, 'account_id' => $accA->id, 'category_id' => $catA->id, 'description' => 'A tx']);
    Transaction::factory()->create(['user_id' => $userB->id, 'account_id' => $accB->id, 'category_id' => $catB->id, 'description' => 'B tx']);

    Auth::login($userA);

    expect(Transaction::count())->toBe(1)
        ->and(Transaction::first()->description)->toBe('A tx');
});

// ─── getTotalAmountAttribute ──────────────────────────────────────────────────

it('getTotalAmount returns amount only when no fee linked', function () {
    $user    = User::factory()->create();
    Auth::login($user);

    $account = Account::factory()->create(['user_id' => $user->id]);
    $cat     = Category::factory()->create(['user_id' => $user->id, 'type' => 'expense']);
    $tx      = Transaction::factory()->create([
        'user_id'     => $user->id,
        'account_id'  => $account->id,
        'category_id' => $cat->id,
        'amount'      => 500,
    ]);

    expect($tx->total_amount)->toBe(500.0);
});

it('getTotalAmount includes fee transaction amount', function () {
    $user    = User::factory()->create();
    Auth::login($user);

    $account = Account::factory()->create(['user_id' => $user->id]);
    $cat     = Category::factory()->create(['user_id' => $user->id, 'type' => 'expense']);

    $feeTx = Transaction::withoutGlobalScope('ownedByUser')->create([
        'user_id'            => $user->id,
        'account_id'         => $account->id,
        'category_id'        => $cat->id,
        'amount'             => 7,
        'date'               => now()->toDateString(),
        'description'        => 'Fee',
        'is_transaction_fee' => true,
    ]);

    $tx = Transaction::factory()->create([
        'user_id'                    => $user->id,
        'account_id'                 => $account->id,
        'category_id'                => $cat->id,
        'amount'                     => 200,
        'related_fee_transaction_id' => $feeTx->id,
    ]);

    $tx->load('feeTransaction');

    expect($tx->total_amount)->toBe(207.0);
});

// ─── hasFee ───────────────────────────────────────────────────────────────────

it('hasFee returns true when a fee is linked', function () {
    $user    = User::factory()->create();
    Auth::login($user);

    $account = Account::factory()->create(['user_id' => $user->id]);
    $cat     = Category::factory()->create(['user_id' => $user->id, 'type' => 'expense']);

    $feeTx = Transaction::withoutGlobalScope('ownedByUser')->create([
        'user_id'            => $user->id,
        'account_id'         => $account->id,
        'category_id'        => $cat->id,
        'amount'             => 7,
        'date'               => now()->toDateString(),
        'description'        => 'Fee',
        'is_transaction_fee' => true,
    ]);

    $tx = Transaction::factory()->create([
        'user_id'                    => $user->id,
        'account_id'                 => $account->id,
        'category_id'                => $cat->id,
        'related_fee_transaction_id' => $feeTx->id,
    ]);

    expect($tx->hasFee())->toBeTrue();
});

it('hasFee returns false when no fee is linked', function () {
    $user    = User::factory()->create();
    Auth::login($user);

    $account = Account::factory()->create(['user_id' => $user->id]);
    $cat     = Category::factory()->create(['user_id' => $user->id, 'type' => 'expense']);
    $tx      = Transaction::factory()->create([
        'user_id'     => $user->id,
        'account_id'  => $account->id,
        'category_id' => $cat->id,
    ]);

    expect($tx->hasFee())->toBeFalse();
});

// ─── SoftDeletes ─────────────────────────────────────────────────────────────

it('soft-deletes a transaction and keeps it in the database', function () {
    $user    = User::factory()->create();
    Auth::login($user);

    $account = Account::factory()->create(['user_id' => $user->id]);
    $cat     = Category::factory()->create(['user_id' => $user->id, 'type' => 'expense']);
    $tx      = Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $cat->id]);

    $tx->delete();

    expect(Transaction::withTrashed()->find($tx->id))->not->toBeNull()
        ->and(Transaction::withTrashed()->find($tx->id)->trashed())->toBeTrue();
});

// ─── Casts ───────────────────────────────────────────────────────────────────

it('casts boolean fields correctly', function () {
    $user    = User::factory()->create();
    Auth::login($user);

    $account = Account::factory()->create(['user_id' => $user->id]);
    $cat     = Category::factory()->create(['user_id' => $user->id, 'type' => 'expense']);
    $tx      = Transaction::factory()->create([
        'user_id'            => $user->id,
        'account_id'         => $account->id,
        'category_id'        => $cat->id,
        'is_transaction_fee' => 1,
        'is_split'           => 0,
        'is_reversal'        => 0,
    ]);

    expect($tx->is_transaction_fee)->toBeTrue()
        ->and($tx->is_split)->toBeFalse()
        ->and($tx->is_reversal)->toBeFalse();
});
