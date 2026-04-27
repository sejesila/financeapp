<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(RefreshDatabase::class);

// ─── index ────────────────────────────────────────────────────────────────────

it('guests cannot access accounts index', function () {
    $this->get(route('accounts.index'))->assertRedirect(route('login'));
});

it('shows only the authenticated users accounts', function () {
    $user  = User::factory()->create();
    $other = User::factory()->create();

    Account::factory()->create(['user_id' => $user->id,  'name' => 'My Cash',  'type' => 'cash', 'is_active' => true]);
    Account::factory()->create(['user_id' => $other->id, 'name' => 'Not Mine', 'type' => 'cash', 'is_active' => true]);

    $this->actingAs($user)
        ->get(route('accounts.index'))
        ->assertOk()
        ->assertSee('My Cash')
        ->assertDontSee('Not Mine');
});

// ─── create ───────────────────────────────────────────────────────────────────

it('shows the create account form to authenticated users', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('accounts.create'))
        ->assertOk();
});

// ─── store ────────────────────────────────────────────────────────────────────

it('creates an account with valid data', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('accounts.store'), [
            'name'            => 'My M-Pesa',
            'type'            => 'mpesa',
            'initial_balance' => 5000,
            'notes'           => 'Test notes',
        ])
        ->assertRedirect(route('accounts.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('accounts', [
        'user_id'         => $user->id,
        'name'            => 'My M-Pesa',
        'type'            => 'mpesa',
        'initial_balance' => 5000,
        'current_balance' => 5000,
    ]);
});

it('fails validation when required fields are missing on store', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('accounts.store'), [])
        ->assertSessionHasErrors(['name', 'type', 'initial_balance']);
});

it('rejects a negative initial_balance', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('accounts.store'), [
            'name'            => 'Bad Account',
            'type'            => 'cash',
            'initial_balance' => -100,
        ])
        ->assertSessionHasErrors(['initial_balance']);
});

it('stores a logo when provided', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('accounts.store'), [
            'name'            => 'With Logo',
            'type'            => 'bank',
            'initial_balance' => 0,
            'logo'            => UploadedFile::fake()->image('logo.png'),
        ])
        ->assertRedirect(route('accounts.index'));

    $account = Account::withoutGlobalScopes()->where('user_id', $user->id)->where('name', 'With Logo')->first();
    expect($account->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($account->logo_path);
});

// ─── show ─────────────────────────────────────────────────────────────────────

it('shows an account that belongs to the user', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('accounts.show', $account))
        ->assertOk()
        ->assertSee($account->name);
});

it('returns 403 when viewing another users account', function () {
    $user  = User::factory()->create();
    $other = User::factory()->create();

    $account = Account::withoutGlobalScopes()->create([
        'user_id'         => $other->id,
        'name'            => 'Other Account',
        'type'            => 'cash',
        'initial_balance' => 0,
        'current_balance' => 0,
        'currency'        => 'KES',
        'is_active'       => true,
    ]);

    $this->actingAs($user)
        ->get(route('accounts.show', $account))
        ->assertForbidden();
});

it('returns 403 when editing another users account', function () {
    $user  = User::factory()->create();
    $other = User::factory()->create();

    $account = Account::withoutGlobalScopes()->create([
        'user_id'         => $other->id,
        'name'            => 'Other Edit Account',
        'type'            => 'cash',
        'initial_balance' => 0,
        'current_balance' => 0,
        'currency'        => 'KES',
        'is_active'       => true,
    ]);

    $this->actingAs($user)
        ->get(route('accounts.edit', $account))
        ->assertForbidden();
});

// ─── update ───────────────────────────────────────────────────────────────────

it('updates an account name and regenerates the slug', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'name' => 'Old Name']);

    $this->actingAs($user)
        ->patch(route('accounts.update', $account), [
            'name'  => 'New Name',
            'notes' => '',
        ])
        ->assertRedirect(route('accounts.show', $account->fresh()));

    expect($account->fresh()->name)->toBe('New Name')
        ->and($account->fresh()->slug)->toBe('new-name');
});

it('returns 403 when updating another users account', function () {
    $user  = User::factory()->create();
    $other = User::factory()->create();

    $account = Account::withoutGlobalScopes()->create([
        'user_id'         => $other->id,
        'name'            => 'Other Update Account',
        'type'            => 'cash',
        'initial_balance' => 0,
        'current_balance' => 0,
        'currency'        => 'KES',
        'is_active'       => true,
    ]);

    $this->actingAs($user)
        ->patch(route('accounts.update', $account), ['name' => 'Hacked'])
        ->assertForbidden();
});

it('removes logo when remove_logo flag is set', function () {
    Storage::fake('public');
    $user    = User::factory()->create();
    $account = Account::factory()->create([
        'user_id'   => $user->id,
        'logo_path' => 'account-logos/logo.png',
    ]);
    Storage::disk('public')->put('account-logos/logo.png', 'fake');

    $this->actingAs($user)
        ->patch(route('accounts.update', $account), [
            'name'        => $account->name,
            'remove_logo' => '1',
        ]);

    expect($account->fresh()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing('account-logos/logo.png');
});

// ─── destroy ──────────────────────────────────────────────────────────────────

it('deletes an account with no transactions', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->delete(route('accounts.destroy', $account))
        ->assertRedirect(route('accounts.index'));

    $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
});

it('prevents deletion of an account that has transactions', function () {
    $user     = User::factory()->create();
    $account  = Account::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['user_id' => $user->id]);

    Transaction::factory()->create(['account_id' => $account->id, 'user_id' => $user->id, 'category_id' => $category->id]);

    $this->actingAs($user)
        ->delete(route('accounts.destroy', $account))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('accounts', ['id' => $account->id]);
});

it('returns 403 when deleting another users account', function () {
    $user  = User::factory()->create();
    $other = User::factory()->create();

    $account = Account::withoutGlobalScopes()->create([
        'user_id'         => $other->id,
        'name'            => 'Other Delete Account',
        'type'            => 'cash',
        'initial_balance' => 0,
        'current_balance' => 0,
        'currency'        => 'KES',
        'is_active'       => true,
    ]);

    $this->actingAs($user)
        ->delete(route('accounts.destroy', $account))
        ->assertForbidden();
});

// ─── transfer form ────────────────────────────────────────────────────────────

it('redirects when user has fewer than 2 accounts on transfer form', function () {
    $user = User::factory()->create();
    Account::factory()->create(['user_id' => $user->id, 'is_active' => true]);

    $this->actingAs($user)
        ->get(route('accounts.transfer'))
        ->assertRedirect(route('accounts.index'))
        ->assertSessionHas('error');
});

// ─── transfer ─────────────────────────────────────────────────────────────────

it('completes a transfer between two accounts', function () {
    $user = User::factory()->create();

    $from = Account::factory()->create([
        'user_id'         => $user->id,
        'type'            => 'cash',
        'current_balance' => 5000,
        'initial_balance' => 5000,
        'is_active'       => true,
    ]);
    $to = Account::factory()->create([
        'user_id'         => $user->id,
        'type'            => 'savings',
        'current_balance' => 0,
        'initial_balance' => 0,
        'is_active'       => true,
    ]);

    $this->actingAs($user)
        ->post(route('accounts.transferPost'), [
            'from_account_id' => $from->id,
            'to_account_id'   => $to->id,
            'amount'          => 1000,
            'date'            => now()->toDateString(),
            'description'     => 'Test transfer',
        ])
        ->assertRedirect(route('accounts.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('transfers', [
        'from_account_id' => $from->id,
        'to_account_id'   => $to->id,
        'amount'          => 1000,
    ]);
});

it('rejects a transfer when from_account equals to_account', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'current_balance' => 5000, 'is_active' => true]);
    Account::factory()->create(['user_id' => $user->id, 'is_active' => true]);

    $this->actingAs($user)
        ->post(route('accounts.transferPost'), [
            'from_account_id' => $account->id,
            'to_account_id'   => $account->id,
            'amount'          => 500,
            'date'            => now()->toDateString(),
        ])
        ->assertSessionHasErrors(['from_account_id']);
});

it('rejects a transfer when balance is insufficient', function () {
    $user = User::factory()->create();

    $from = Account::factory()->create(['user_id' => $user->id, 'type' => 'cash', 'current_balance' => 100, 'is_active' => true]);
    $to   = Account::factory()->create(['user_id' => $user->id, 'type' => 'cash', 'current_balance' => 0,   'is_active' => true]);

    $this->actingAs($user)
        ->post(route('accounts.transferPost'), [
            'from_account_id' => $from->id,
            'to_account_id'   => $to->id,
            'amount'          => 9999,
            'date'            => now()->toDateString(),
        ])
        ->assertSessionHasErrors(['amount']);
});

it('charges M-Pesa withdrawal fee when transferring from mpesa to cash', function () {
    $user = User::factory()->create();

    $mpesa = Account::factory()->create([
        'user_id'         => $user->id,
        'type'            => 'mpesa',
        'current_balance' => 999999,
        'initial_balance' => 999999,
        'is_active'       => true,
    ]);
    $cash = Account::factory()->create([
        'user_id'         => $user->id,
        'type'            => 'cash',
        'current_balance' => 0,
        'initial_balance' => 0,
        'is_active'       => true,
    ]);

    $this->actingAs($user)
        ->post(route('accounts.transferPost'), [
            'from_account_id' => $mpesa->id,
            'to_account_id'   => $cash->id,
            'amount'          => 500,
            'date'            => now()->toDateString(),
        ])
        ->assertRedirect(route('accounts.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('transactions', [
        'account_id'         => $mpesa->id,
        'is_transaction_fee' => true,
    ]);
});

it('prevents transferring from another users account', function () {
    $user  = User::factory()->create();
    $other = User::factory()->create();

    $from = Account::factory()->create(['user_id' => $other->id, 'type' => 'cash', 'current_balance' => 5000, 'is_active' => true]);
    $to   = Account::factory()->create(['user_id' => $user->id,  'type' => 'cash', 'current_balance' => 0,   'is_active' => true]);
    Account::factory()->create(['user_id' => $user->id, 'is_active' => true]);

    $this->actingAs($user)
        ->post(route('accounts.transferPost'), [
            'from_account_id' => $from->id,
            'to_account_id'   => $to->id,
            'amount'          => 100,
            'date'            => now()->toDateString(),
        ])
        ->assertForbidden();
});

// ─── top-up ───────────────────────────────────────────────────────────────────

it('tops up an account with a valid income category', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create([
        'user_id'         => $user->id,
        'type'            => 'mpesa',
        'current_balance' => 0,
        'initial_balance' => 0,
    ]);

    $parent = Category::factory()->create([
        'user_id'   => $user->id,
        'name'      => 'Income',
        'type'      => 'income',
        'parent_id' => null,
        'is_active' => true,
    ]);
    $category = Category::factory()->create([
        'user_id'   => $user->id,
        'type'      => 'income',
        'name'      => 'Freelance',
        'parent_id' => $parent->id,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('accounts.topup.store', $account), [
            'amount'      => 3000,
            'category_id' => $category->id,
            'date'        => now()->toDateString(),
        ])
        ->assertRedirect(route('accounts.show', $account))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('transactions', [
        'account_id'  => $account->id,
        'amount'      => 3000,
        'category_id' => $category->id,
    ]);
});

it('blocks top-up with a system-reserved category', function () {
    $user     = User::factory()->create();
    $account  = Account::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create([
        'user_id'   => $user->id,
        'name'      => 'Loan Receipt',
        'type'      => 'liability',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('accounts.topup.store', $account), [
            'amount'      => 5000,
            'category_id' => $category->id,
            'date'        => now()->toDateString(),
        ])
        ->assertSessionHas('error');
});

it('returns 403 when topping up another users account', function () {
    $user  = User::factory()->create();
    $other = User::factory()->create();

    $account = Account::withoutGlobalScopes()->create([
        'user_id'         => $other->id,
        'name'            => 'Other Topup Account',
        'type'            => 'cash',
        'initial_balance' => 0,
        'current_balance' => 0,
        'currency'        => 'KES',
        'is_active'       => true,
    ]);
    $cat = Category::factory()->create(['user_id' => $user->id, 'type' => 'income', 'is_active' => true]);

    $this->actingAs($user)
        ->post(route('accounts.topup.store', $account), [
            'amount'      => 1000,
            'category_id' => $cat->id,
            'date'        => now()->toDateString(),
        ])
        ->assertForbidden();
});

// ─── Sacco Dividends top-up ───────────────────────────────────────────────────
//
// Rules enforced by AccountController:
//   1. Only visible/usable between 10 April and 10 May (inclusive).
//   2. Once used once in the current calendar year it disappears from the
//      dropdown and is rejected server-side — even if the window is still open.

function saccoSetup(): array
{
    $user    = User::factory()->create(); // observer seeds all categories

    $account = Account::factory()->create([
        'user_id'         => $user->id,
        'type'            => 'mpesa',
        'current_balance' => 0,
        'initial_balance' => 0,
        'is_active'       => true,
    ]);

    $saccoCategory = Category::where('user_id', $user->id)
        ->where('name', 'Sacco Dividends')
        ->firstOrFail();

    return [$user, $account, $saccoCategory];
}

it('sacco dividends appears in top-up form during the allowed window', function () {
    [$user, $account] = saccoSetup();

    $this->travelTo(now()->setDate(now()->year, 4, 20));

    $this->actingAs($user)
        ->get(route('accounts.topup', $account))
        ->assertOk()
        ->assertSee('Sacco Dividends')
        ->assertViewHas('showSaccoDividends', true);
});

it('sacco dividends does not appear in top-up form outside the allowed window', function () {
    [$user, $account] = saccoSetup();

    $this->travelTo(now()->setDate(now()->year, 6, 15));

    $this->actingAs($user)
        ->get(route('accounts.topup', $account))
        ->assertOk()
        ->assertDontSee('Sacco Dividends')
        ->assertViewHas('showSaccoDividends', false);
});

it('sacco dividends appears on 10 april (window start boundary)', function () {
    [$user, $account] = saccoSetup();

    $this->travelTo(now()->setDate(now()->year, 4, 10));

    $this->actingAs($user)
        ->get(route('accounts.topup', $account))
        ->assertOk()
        ->assertViewHas('showSaccoDividends', true);
});

it('sacco dividends appears on 10 may (window end boundary)', function () {
    [$user, $account] = saccoSetup();

    $this->travelTo(now()->setDate(now()->year, 5, 10));

    $this->actingAs($user)
        ->get(route('accounts.topup', $account))
        ->assertOk()
        ->assertViewHas('showSaccoDividends', true);
});

it('sacco dividends disappears on 11 may (day after window closes)', function () {
    [$user, $account] = saccoSetup();

    $this->travelTo(now()->setDate(now()->year, 5, 11));

    $this->actingAs($user)
        ->get(route('accounts.topup', $account))
        ->assertOk()
        ->assertViewHas('showSaccoDividends', false);
});

it('can top up using sacco dividends during the allowed window', function () {
    [$user, $account, $saccoCategory] = saccoSetup();

    $this->travelTo(now()->setDate(now()->year, 4, 25));

    $this->actingAs($user)
        ->post(route('accounts.topup.store', $account), [
            'amount'      => 12000,
            'category_id' => $saccoCategory->id,
            'date'        => now()->toDateString(),
        ])
        ->assertRedirect(route('accounts.show', $account))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('transactions', [
        'user_id'     => $user->id,
        'account_id'  => $account->id,
        'category_id' => $saccoCategory->id,
        'amount'      => 12000,
    ]);
});

it('rejects sacco dividends top-up when submitted outside the allowed window', function () {
    [$user, $account, $saccoCategory] = saccoSetup();

    $this->travelTo(now()->setDate(now()->year, 7, 1));

    $this->actingAs($user)
        ->post(route('accounts.topup.store', $account), [
            'amount'      => 5000,
            'category_id' => $saccoCategory->id,
            'date'        => now()->toDateString(),
        ])
        ->assertSessionHas('error');

    $this->assertDatabaseCount('transactions', 0);
});

it('sacco dividends disappears from dropdown after first use even within the window', function () {
    [$user, $account, $saccoCategory] = saccoSetup();

    $this->travelTo(now()->setDate(now()->year, 4, 25));

    $this->actingAs($user)
        ->post(route('accounts.topup.store', $account), [
            'amount'      => 8000,
            'category_id' => $saccoCategory->id,
            'date'        => now()->toDateString(),
        ])
        ->assertSessionHas('success');

    // Confirm the transaction was actually persisted with the correct category
    $this->assertDatabaseHas('transactions', [
        'user_id'     => $user->id,
        'category_id' => $saccoCategory->id,
    ]);

    $this->actingAs($user)
        ->get(route('accounts.topup', $account))
        ->assertOk()
        ->assertDontSee('Sacco Dividends available')
        ->assertViewHas('showSaccoDividends', false);
});

it('rejects a second sacco dividends top-up in the same year even within the window', function () {
    [$user, $account, $saccoCategory] = saccoSetup();

    $this->travelTo(now()->setDate(now()->year, 4, 25));

    $this->actingAs($user)
        ->post(route('accounts.topup.store', $account), [
            'amount'      => 8000,
            'category_id' => $saccoCategory->id,
            'date'        => now()->toDateString(),
        ])
        ->assertSessionHas('success');

    $this->actingAs($user)
        ->post(route('accounts.topup.store', $account), [
            'amount'      => 3000,
            'category_id' => $saccoCategory->id,
            'date'        => now()->toDateString(),
        ])
        ->assertSessionHas('error');

    $this->assertDatabaseCount('transactions', 1);
});

it('shows the sacco dividends info banner during the window', function () {
    [$user, $account] = saccoSetup();

    $this->travelTo(now()->setDate(now()->year, 4, 20));

    $this->actingAs($user)
        ->get(route('accounts.topup', $account))
        ->assertOk()
        ->assertSee('Sacco Dividends available');
});

it('does not show the sacco dividends banner outside the window', function () {
    [$user, $account] = saccoSetup();

    $this->travelTo(now()->setDate(now()->year, 8, 1));

    $this->actingAs($user)
        ->get(route('accounts.topup', $account))
        ->assertOk()
        ->assertDontSee('Sacco Dividends available');
});

it('does not show the sacco dividends banner after it has been used', function () {
    [$user, $account, $saccoCategory] = saccoSetup();

    $this->travelTo(now()->setDate(now()->year, 4, 25));

    $this->actingAs($user)
        ->post(route('accounts.topup.store', $account), [
            'amount'      => 5000,
            'category_id' => $saccoCategory->id,
            'date'        => now()->toDateString(),
        ])
        ->assertSessionHas('success');

    // Confirm the transaction was persisted
    $this->assertDatabaseHas('transactions', [
        'user_id'     => $user->id,
        'category_id' => $saccoCategory->id,
    ]);

    $this->actingAs($user)
        ->get(route('accounts.topup', $account))
        ->assertOk()
        ->assertViewHas('showSaccoDividends', false);
});

it('sacco dividends for one user is independent of another users usage', function () {
    [$userA, $accountA, $saccoCategoryA] = saccoSetup();
    [$userB, $accountB]                  = saccoSetup();

    $this->travelTo(now()->setDate(now()->year, 4, 25));

    $this->actingAs($userA)
        ->post(route('accounts.topup.store', $accountA), [
            'amount'      => 5000,
            'category_id' => $saccoCategoryA->id,
            'date'        => now()->toDateString(),
        ])
        ->assertSessionHas('success');

    $this->actingAs($userB)
        ->get(route('accounts.topup', $accountB))
        ->assertOk()
        ->assertViewHas('showSaccoDividends', true)
        ->assertSee('Sacco Dividends');
});
