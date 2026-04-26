<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeUser(): User
{
    return User::factory()->create();
}

function cashAccount(User $user, float $balance = 10000): Account
{
    return Account::factory()->create([
        'user_id'         => $user->id,
        'type'            => 'cash',
        'current_balance' => $balance,
        'initial_balance' => $balance,
        'is_active'       => true,
    ]);
}

function mpesaAccount(User $user, float $balance = 10000): Account
{
    return Account::factory()->create([
        'user_id'         => $user->id,
        'type'            => 'mpesa',
        'current_balance' => $balance,
        'initial_balance' => $balance,
        'is_active'       => true,
    ]);
}

function expenseCat(User $user, string $name = 'Food'): Category
{
    return Category::factory()->create([
        'user_id'   => $user->id,
        'type'      => 'expense',
        'name'      => $name,
        'is_active' => true,
    ]);
}

function incomeCat(User $user, string $name = 'Salary'): Category
{
    return Category::factory()->create([
        'user_id'   => $user->id,
        'type'      => 'income',
        'name'      => $name,
        'is_active' => true,
    ]);
}

// ─── index ────────────────────────────────────────────────────────────────────

it('guests are redirected from transactions index', function () {
    $this->get(route('transactions.index'))->assertRedirect(route('login'));
});

it('shows only the authenticated users transactions on index', function () {
    $user  = makeUser();
    $other = makeUser();

    $account    = cashAccount($user);
    $otherAcct  = cashAccount($other);
    $userCat    = expenseCat($user);
    $otherCat   = expenseCat($other);

    Transaction::factory()->create(['user_id' => $user->id,  'account_id' => $account->id,   'category_id' => $userCat->id,  'description' => 'My Transaction']);
    Transaction::factory()->create(['user_id' => $other->id, 'account_id' => $otherAcct->id, 'category_id' => $otherCat->id, 'description' => 'Other Transaction']);

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertSee('My Transaction')
        ->assertDontSee('Other Transaction');
});

// ─── create ───────────────────────────────────────────────────────────────────

it('shows the create transaction form', function () {
    $this->actingAs(makeUser())
        ->get(route('transactions.create'))
        ->assertOk();
});

// ─── store ────────────────────────────────────────────────────────────────────

it('stores a valid expense transaction', function () {
    $user     = makeUser();
    $account  = cashAccount($user);
    $category = expenseCat($user);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'date'        => now()->toDateString(),
            'description' => 'Lunch',
            'amount'      => 500,
            'category_id' => $category->id,
            'account_id'  => $account->id,
        ])
        ->assertRedirect(route('transactions.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('transactions', [
        'user_id'     => $user->id,
        'description' => 'Lunch',
        'amount'      => 500,
    ]);
});

it('fails validation when required fields are missing on store', function () {
    $this->actingAs(makeUser())
        ->post(route('transactions.store'), [])
        ->assertSessionHasErrors(['date', 'description', 'amount', 'category_id', 'account_id']);
});

it('rejects a zero or negative amount', function () {
    $user     = makeUser();
    $account  = cashAccount($user);
    $category = expenseCat($user);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'date'        => now()->toDateString(),
            'description' => 'Bad',
            'amount'      => 0,
            'category_id' => $category->id,
            'account_id'  => $account->id,
        ])
        ->assertSessionHasErrors(['amount']);
});

it('rejects using another users account', function () {
    $user    = makeUser();
    $other   = makeUser();
    $account = cashAccount($other);
    $cat     = expenseCat($user);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'date'        => now()->toDateString(),
            'description' => 'Steal',
            'amount'      => 100,
            'category_id' => $cat->id,
            'account_id'  => $account->id,
        ])
        ->assertSessionHasErrors(['account_id']);
});

it('creates a fee transaction alongside an M-Pesa expense', function () {
    $user     = makeUser();
    $account  = mpesaAccount($user);
    $category = expenseCat($user);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'date'              => now()->toDateString(),
            'description'       => 'Send money',
            'amount'            => 200,
            'category_id'       => $category->id,
            'account_id'        => $account->id,
            'mobile_money_type' => 'send_money',
        ])
        ->assertRedirect(route('transactions.index'));

    // 200 KES → 7 KES fee
    $this->assertDatabaseHas('transactions', [
        'account_id'         => $account->id,
        'amount'             => 7,
        'is_transaction_fee' => true,
    ]);
});

// ─── show ─────────────────────────────────────────────────────────────────────

it('shows a transaction belonging to the authenticated user', function () {
    $user        = makeUser();
    $account     = cashAccount($user);
    $category    = expenseCat($user);
    $transaction = Transaction::factory()->create([
        'user_id'     => $user->id,
        'account_id'  => $account->id,
        'category_id' => $category->id,
        'description' => 'Show me',
    ]);

    $this->actingAs($user)
        ->get(route('transactions.show', $transaction))
        ->assertOk()
        ->assertSee('Show me');
});

it('returns 403 when viewing another users transaction', function () {
    $user    = makeUser();
    $other   = makeUser();
    $account = cashAccount($other);
    $cat     = expenseCat($other);
    $tx      = Transaction::factory()->create([
        'user_id'     => $other->id,
        'account_id'  => $account->id,
        'category_id' => $cat->id,
    ]);

    $this->actingAs($user)
        ->get(route('transactions.show', $tx))
        ->assertForbidden();
});

// ─── edit ─────────────────────────────────────────────────────────────────────

it('shows the edit form for the users own transaction', function () {
    $user    = makeUser();
    $account = cashAccount($user);
    $cat     = expenseCat($user);
    $tx      = Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $cat->id]);

    $this->actingAs($user)
        ->get(route('transactions.edit', $tx))
        ->assertOk();
});

it('prevents editing a system-generated fee transaction', function () {
    $user    = makeUser();
    $account = mpesaAccount($user);
    $cat     = expenseCat($user);
    $feeTx   = Transaction::factory()->create([
        'user_id'            => $user->id,
        'account_id'         => $account->id,
        'category_id'        => $cat->id,
        'is_transaction_fee' => true,
    ]);

    $this->actingAs($user)
        ->get(route('transactions.edit', $feeTx))
        ->assertRedirect()
        ->assertSessionHas('error');
});

// ─── update ───────────────────────────────────────────────────────────────────

it('updates a transaction successfully', function () {
    $user    = makeUser();
    $account = cashAccount($user);
    $cat     = expenseCat($user);
    $tx      = Transaction::factory()->create([
        'user_id'     => $user->id,
        'account_id'  => $account->id,
        'category_id' => $cat->id,
        'amount'      => 100,
        'description' => 'Original',
    ]);

    $this->actingAs($user)
        ->patch(route('transactions.update', $tx), [
            'date'        => now()->toDateString(),
            'description' => 'Updated description',
            'amount'      => 200,
            'category_id' => $cat->id,
            'account_id'  => $account->id,
        ])
        ->assertRedirect(route('transactions.show', $tx));

    expect($tx->fresh()->description)->toBe('Updated description')
        ->and((float) $tx->fresh()->amount)->toBe(200.0);
});

it('returns 403 when updating another users transaction', function () {
    $user    = makeUser();
    $other   = makeUser();
    $account = cashAccount($other);
    $cat     = expenseCat($other);
    $tx      = Transaction::factory()->create(['user_id' => $other->id, 'account_id' => $account->id, 'category_id' => $cat->id]);

    $this->actingAs($user)
        ->patch(route('transactions.update', $tx), [
            'date'        => now()->toDateString(),
            'description' => 'Hacked',
            'amount'      => 100,
            'category_id' => $cat->id,
            'account_id'  => $account->id,
        ])
        ->assertForbidden();
});

// ─── destroy ──────────────────────────────────────────────────────────────────

it('soft-deletes a transaction', function () {
    $user    = makeUser();
    $account = cashAccount($user);
    $cat     = expenseCat($user);
    $tx      = Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $cat->id]);

    $this->actingAs($user)
        ->delete(route('transactions.destroy', $tx))
        ->assertRedirect(route('transactions.index'))
        ->assertSessionHas('success');

    $this->assertSoftDeleted('transactions', ['id' => $tx->id]);
});

it('prevents deleting a system-generated fee transaction directly', function () {
    $user    = makeUser();
    $account = cashAccount($user);
    $cat     = expenseCat($user);
    $feeTx   = Transaction::factory()->create([
        'user_id'            => $user->id,
        'account_id'         => $account->id,
        'category_id'        => $cat->id,
        'is_transaction_fee' => true,
    ]);

    $this->actingAs($user)
        ->delete(route('transactions.destroy', $feeTx))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('transactions', ['id' => $feeTx->id, 'deleted_at' => null]);
});

it('returns 403 when deleting another users transaction', function () {
    $user    = makeUser();
    $other   = makeUser();
    $account = cashAccount($other);
    $cat     = expenseCat($other);
    $tx      = Transaction::factory()->create(['user_id' => $other->id, 'account_id' => $account->id, 'category_id' => $cat->id]);

    $this->actingAs($user)
        ->delete(route('transactions.destroy', $tx))
        ->assertForbidden();
});

// ─── restore ──────────────────────────────────────────────────────────────────

it('restores a soft-deleted transaction', function () {
    $user    = makeUser();
    $account = cashAccount($user);
    $cat     = expenseCat($user);
    $tx      = Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $cat->id]);
    $tx->delete();

    $this->actingAs($user)
        ->post(route('transactions.restore', $tx->id))
        ->assertRedirect(route('transactions.index'))
        ->assertSessionHas('success');

    expect($tx->fresh()->deleted_at)->toBeNull();
});

// ─── Date Filtering ───────────────────────────────────────────────────────────

it('filters transactions to today only', function () {
    $user    = makeUser();
    $account = cashAccount($user);
    $cat     = expenseCat($user);

    Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $cat->id, 'date' => now(),             'description' => 'Today tx']);
    Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $cat->id, 'date' => now()->subDays(5),  'description' => 'Old tx']);

    $this->actingAs($user)
        ->get(route('transactions.index', ['filter' => 'today']))
        ->assertOk()
        ->assertSee('Today tx')
        ->assertDontSee('Old tx');
});

it('filters transactions to this month', function () {
    $user    = makeUser();
    $account = cashAccount($user);
    $cat     = expenseCat($user);

    Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $cat->id, 'date' => now(),              'description' => 'This month tx']);
    Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $cat->id, 'date' => now()->subMonths(2), 'description' => 'Old month tx']);

    $this->actingAs($user)
        ->get(route('transactions.index', ['filter' => 'this_month']))
        ->assertOk()
        ->assertSee('This month tx')
        ->assertDontSee('Old month tx');
});
