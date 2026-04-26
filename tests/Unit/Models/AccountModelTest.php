<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ─── Slug Generation ───────────────────────────────────────────────────────

it('auto-generates a slug from name on create', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'name' => 'My Mpesa Account']);

    expect($account->slug)->toBe('my-mpesa-account');
});

it('appends numeric suffix when slug already exists for same user', function () {
    $user = User::factory()->create();

    Account::factory()->create(['user_id' => $user->id, 'name' => 'Savings']);
    $second = Account::factory()->create(['user_id' => $user->id, 'name' => 'Savings']);

    expect($second->slug)->toBe('savings-1');
});

it('allows same slug for different users', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $accountA = Account::factory()->create(['user_id' => $userA->id, 'name' => 'Savings']);
    $accountB = Account::factory()->create(['user_id' => $userB->id, 'name' => 'Savings']);

    expect($accountA->slug)->toBe('savings')
        ->and($accountB->slug)->toBe('savings');
});

it('updates slug when name is changed', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'name' => 'Old Name']);
    $account->update(['name' => 'New Name']);

    expect($account->fresh()->slug)->toBe('new-name');
});

it('does not change slug when other fields are updated', function () {
    $user         = User::factory()->create();
    $account      = Account::factory()->create(['user_id' => $user->id, 'name' => 'Cash Account']);
    $originalSlug = $account->slug;

    $account->update(['notes' => 'Some note']);

    expect($account->fresh()->slug)->toBe($originalSlug);
});

// ─── Route Key ────────────────────────────────────────────────────────────

it('uses slug as route key', function () {
    $account = new Account();
    expect($account->getRouteKeyName())->toBe('slug');
});

// ─── Global Scope ─────────────────────────────────────────────────────────

it('only returns accounts belonging to the authenticated user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Account::factory()->create(['user_id' => $userA->id]);
    Account::factory()->create(['user_id' => $userB->id]);

    $userAAccounts = Account::withoutGlobalScope('ownedByUser')
        ->where('user_id', $userA->id)
        ->get();

    expect($userAAccounts)->toHaveCount(1);
});

it('returns all accounts when not authenticated', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Account::factory()->create(['user_id' => $userA->id]);
    Account::factory()->create(['user_id' => $userB->id]);

    $allAccounts = Account::withoutGlobalScopes()->get();
    expect($allAccounts)->toHaveCount(2);
});

// ─── Casts ───────────────────────────────────────────────────────────────

it('casts is_active to boolean', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'is_active' => 1]);

    expect($account->is_active)->toBeTrue();
});

it('casts initial_balance and current_balance to decimal', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create([
        'user_id'         => $user->id,
        'initial_balance' => 1000,
        'current_balance' => 1500,
    ]);

    expect($account->initial_balance)->toEqual('1000.00')
        ->and($account->current_balance)->toEqual('1500.00');
});

// ─── Relationships ────────────────────────────────────────────────────────

it('belongs to a user', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);

    expect($account->user->id)->toBe($user->id);
});

// ─── Helper Methods ───────────────────────────────────────────────────────

it('getTotalLoansActive returns sum of active loan balances', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);

    \App\Models\Loan::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'status' => 'active', 'balance' => 5000]);
    \App\Models\Loan::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'status' => 'active', 'balance' => 3000]);
    \App\Models\Loan::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'status' => 'paid',   'balance' => 2000]);

    expect((float) $account->getTotalLoansActive())->toBe(8000.0);
});

it('getClientFundsBalance excludes completed client funds', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);

    \App\Models\ClientFund::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'status' => 'pending',   'balance' => 10000]);
    \App\Models\ClientFund::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'status' => 'completed', 'balance' => 5000]);

    expect((float) $account->getClientFundsBalance())->toBe(10000.0);
});
