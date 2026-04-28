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

it('separates regular accounts from savings accounts on the index', function () {
    $user = User::factory()->create();

    Account::factory()->create(['user_id' => $user->id, 'name' => 'My Cash',    'type' => 'cash',    'is_active' => true, 'current_balance' => 1000]);
    Account::factory()->create(['user_id' => $user->id, 'name' => 'My Savings', 'type' => 'savings', 'is_active' => true, 'current_balance' => 5000]);

    $response = $this->actingAs($user)->get(route('accounts.index'));

    $response->assertOk()
        ->assertViewHas('accounts')
        ->assertViewHas('savingsAccounts')
        ->assertViewHas('totalBalance')
        ->assertViewHas('totalSavings');

    // Regular accounts total should include only non-savings
    expect($response->viewData('totalBalance'))->toBe('1000.00')
        ->and($response->viewData('totalSavings'))->toBe('5000.00');
});

it('excludes inactive accounts from the index totals', function () {
    $user = User::factory()->create();

    Account::factory()->create(['user_id' => $user->id, 'type' => 'cash', 'is_active' => true,  'current_balance' => 2000]);
    Account::factory()->create(['user_id' => $user->id, 'type' => 'cash', 'is_active' => false, 'current_balance' => 9000]);

    $response = $this->actingAs($user)->get(route('accounts.index'));

    expect($response->viewData('totalBalance'))->toBe('2000.00');
});

it('filters recent transfers by search term on the index', function () {
    $user = User::factory()->create();

    // ✅ Fix — use cash→mpesa (or mpesa→cash, or savings→cash):
    $from = Account::factory()->create([
        'user_id'         => $user->id,
        'type'            => 'cash',
        'current_balance' => 5000,
        'initial_balance' => 5000,
        'is_active'       => true,
    ]);
    $to = Account::factory()->create([
        'user_id'         => $user->id,
        'type'            => 'mpesa',   // ← change savings to mpesa
        'current_balance' => 0,
        'initial_balance' => 0,
        'is_active'       => true,
    ]);

    Transfer::create([
        'from_account_id' => $from->id,
        'to_account_id'   => $to->id,
        'amount'          => 500,
        'date'            => now()->toDateString(),
        'description'     => 'Groceries top-up',
        'user_id'         => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('accounts.index', ['transfer_search' => 'Groceries']))
        ->assertOk()
        ->assertSee('Groceries top-up');

    $this->actingAs($user)
        ->get(route('accounts.index', ['transfer_search' => 'NonExistent']))
        ->assertOk()
        ->assertDontSee('Groceries top-up');
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

it('sets initial_balance and current_balance to the same value on creation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('accounts.store'), [
        'name'            => 'Sync Test',
        'type'            => 'cash',
        'initial_balance' => 7500,
    ]);

    $account = Account::withoutGlobalScopes()->where('user_id', $user->id)->first();
    expect((float) $account->initial_balance)->toBe(7500.0)
        ->and((float) $account->current_balance)->toBe(7500.0);
});

it('rejects a non-image file as a logo', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('accounts.store'), [
            'name'            => 'Bad Logo',
            'type'            => 'cash',
            'initial_balance' => 0,
            'logo'            => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors(['logo']);
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

it('shows correct stats on the account detail page', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'cash', 'current_balance' => 0]);

    $incomeParent = Category::factory()->create(['user_id' => $user->id, 'name' => 'Income',  'type' => 'income',  'parent_id' => null]);
    $expenseParent = Category::factory()->create(['user_id' => $user->id, 'name' => 'Expense', 'type' => 'expense', 'parent_id' => null]);

    $incomeCat  = Category::factory()->create(['user_id' => $user->id, 'type' => 'income',  'parent_id' => $incomeParent->id]);
    $expenseCat = Category::factory()->create(['user_id' => $user->id, 'type' => 'expense', 'parent_id' => $expenseParent->id]);

    Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $incomeCat->id,  'amount' => 10000, 'date' => now()]);
    Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $expenseCat->id, 'amount' => 3000,  'date' => now()]);

    $response = $this->actingAs($user)->get(route('accounts.show', $account));

    $response->assertOk()
        ->assertViewHas('totalIncome', 10000.0)
        ->assertViewHas('totalExpenses', 3000.0)
        ->assertViewHas('totalTransactions', 2);
});

it('paginates transactions on the account show page', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);
    $cat     = Category::factory()->create(['user_id' => $user->id, 'type' => 'expense']);

    Transaction::factory()->count(25)->create([
        'user_id'     => $user->id,
        'account_id'  => $account->id,
        'category_id' => $cat->id,
    ]);

    $this->actingAs($user)
        ->get(route('accounts.show', $account))
        ->assertOk()
        ->assertViewHas('transactions');
});

it('filters transactions by search term on the show page', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);
    $cat     = Category::factory()->create(['user_id' => $user->id, 'type' => 'expense']);

    Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $cat->id, 'description' => 'Lunch at Java', 'amount' => 500]);
    Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $cat->id, 'description' => 'Netflix subscription', 'amount' => 1300]);

    $this->actingAs($user)
        ->get(route('accounts.show', $account) . '?search=Lunch&tab=transactions')
        ->assertOk()
        ->assertSee('Lunch at Java')
        ->assertDontSee('Netflix subscription');
});

it('sorts transactions by amount on the show page', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);
    $cat     = Category::factory()->create(['user_id' => $user->id, 'type' => 'expense']);

    Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $cat->id, 'amount' => 100]);
    Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $cat->id, 'amount' => 9000]);

    $response = $this->actingAs($user)
        ->get(route('accounts.show', $account) . '?tx_sort=amount&tx_dir=asc');

    $response->assertOk();
    $transactions = $response->viewData('transactions');
    expect((float) $transactions->first()->amount)->toBe(100.0)
        ->and((float) $transactions->last()->amount)->toBe(9000.0);
});

it('falls back to safe sort column when an invalid tx_sort is provided', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('accounts.show', $account) . '?tx_sort=injected_column&tx_dir=asc')
        ->assertOk()
        ->assertViewHas('txSort', 'date');
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

it('replaces existing logo when a new one is uploaded', function () {
    Storage::fake('public');
    $user    = User::factory()->create();
    $account = Account::factory()->create([
        'user_id'   => $user->id,
        'logo_path' => 'account-logos/old-logo.png',
    ]);
    Storage::disk('public')->put('account-logos/old-logo.png', 'old-fake');

    $this->actingAs($user)
        ->patch(route('accounts.update', $account), [
            'name' => $account->name,
            'logo' => UploadedFile::fake()->image('new-logo.png'),
        ]);

    Storage::disk('public')->assertMissing('account-logos/old-logo.png');
    $newPath = $account->fresh()->logo_path;
    expect($newPath)->not->toBeNull()->not->toBe('account-logos/old-logo.png');
    Storage::disk('public')->assertExists($newPath);
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

it('deletes the logo file from storage when an account is deleted', function () {
    Storage::fake('public');
    $user    = User::factory()->create();
    $account = Account::factory()->create([
        'user_id'   => $user->id,
        'logo_path' => 'account-logos/bye.png',
    ]);
    Storage::disk('public')->put('account-logos/bye.png', 'fake');

    $this->actingAs($user)->delete(route('accounts.destroy', $account));

    Storage::disk('public')->assertMissing('account-logos/bye.png');
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

it('shows the transfer form when the user has at least two accounts', function () {
    $user = User::factory()->create();
    Account::factory()->count(2)->create(['user_id' => $user->id, 'is_active' => true, 'current_balance' => 1000]);

    $this->actingAs($user)
        ->get(route('accounts.transfer'))
        ->assertOk()
        ->assertViewHas('sourceAccounts')
        ->assertViewHas('destinationAccounts');
});

it('only shows accounts with balance >= 1 as source on transfer form', function () {
    $user = User::factory()->create();

    Account::factory()->create(['user_id' => $user->id, 'name' => 'Has Funds', 'is_active' => true, 'current_balance' => 1000]);
    Account::factory()->create(['user_id' => $user->id, 'name' => 'Empty',     'is_active' => true, 'current_balance' => 0]);

    $response = $this->actingAs($user)->get(route('accounts.transfer'));

    $sourceNames = $response->viewData('sourceAccounts')->pluck('name');
    expect($sourceNames)->toContain('Has Funds')
        ->and($sourceNames)->not->toContain('Empty');
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
        'type'            => 'mpesa',
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

// ─── Transfer rule: Cash → Savings blocked ────────────────────────────────────

it('blocks a direct transfer from cash to savings', function () {
    $user = User::factory()->create();

    $cash    = Account::factory()->create(['user_id' => $user->id, 'type' => 'cash',    'current_balance' => 5000, 'is_active' => true]);
    $savings = Account::factory()->create(['user_id' => $user->id, 'type' => 'savings', 'current_balance' => 0,    'is_active' => true]);

    $this->actingAs($user)
        ->post(route('accounts.transferPost'), [
            'from_account_id' => $cash->id,
            'to_account_id'   => $savings->id,
            'amount'          => 1000,
            'date'            => now()->toDateString(),
        ])
        ->assertSessionHasErrors(['to_account_id']);

    $this->assertDatabaseCount('transfers', 0);
});

// ─── Transfer rule: Savings → Bank blocked ────────────────────────────────────

it('blocks a transfer from savings to bank', function () {
    $user = User::factory()->create();

    $savings = Account::factory()->create(['user_id' => $user->id, 'type' => 'savings', 'current_balance' => 5000, 'is_active' => true]);
    $bank    = Account::factory()->create(['user_id' => $user->id, 'type' => 'bank',    'current_balance' => 0,    'is_active' => true]);

    $this->actingAs($user)
        ->post(route('accounts.transferPost'), [
            'from_account_id' => $savings->id,
            'to_account_id'   => $bank->id,
            'amount'          => 1000,
            'date'            => now()->toDateString(),
        ])
        ->assertSessionHasErrors(['to_account_id']);

    $this->assertDatabaseCount('transfers', 0);
});

// ─── Transfer rule: Savings → Savings blocked ────────────────────────────────

it('blocks a transfer from savings to another savings account', function () {
    $user = User::factory()->create();

    $savings1 = Account::factory()->create(['user_id' => $user->id, 'type' => 'savings', 'current_balance' => 5000, 'is_active' => true]);
    $savings2 = Account::factory()->create(['user_id' => $user->id, 'type' => 'savings', 'current_balance' => 0,    'is_active' => true]);

    $this->actingAs($user)
        ->post(route('accounts.transferPost'), [
            'from_account_id' => $savings1->id,
            'to_account_id'   => $savings2->id,
            'amount'          => 500,
            'date'            => now()->toDateString(),
        ])
        ->assertSessionHasErrors(['to_account_id']);
});

// ─── Transfer rule: Savings → Cash/MoMo allowed ──────────────────────────────

it('allows a transfer from savings to cash', function () {
    $user = User::factory()->create();

    $savings = Account::factory()->create(['user_id' => $user->id, 'type' => 'savings', 'current_balance' => 5000, 'initial_balance' => 5000, 'is_active' => true]);
    $cash    = Account::factory()->create(['user_id' => $user->id, 'type' => 'cash',    'current_balance' => 0,    'initial_balance' => 0,    'is_active' => true]);

    $this->actingAs($user)
        ->post(route('accounts.transferPost'), [
            'from_account_id' => $savings->id,
            'to_account_id'   => $cash->id,
            'amount'          => 1000,
            'date'            => now()->toDateString(),
        ])
        ->assertRedirect(route('accounts.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('transfers', ['from_account_id' => $savings->id, 'to_account_id' => $cash->id]);
});

// ─── Fee: M-Pesa → Cash (withdrawal) ─────────────────────────────────────────

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

it('rejects an M-Pesa withdrawal below KES 50', function () {
    $user = User::factory()->create();

    $mpesa = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 1000, 'initial_balance' => 1000, 'is_active' => true]);
    $cash  = Account::factory()->create(['user_id' => $user->id, 'type' => 'cash',  'current_balance' => 0,    'is_active' => true]);

    $this->actingAs($user)
        ->post(route('accounts.transferPost'), [
            'from_account_id' => $mpesa->id,
            'to_account_id'   => $cash->id,
            'amount'          => 49,
            'date'            => now()->toDateString(),
        ])
        ->assertSessionHasErrors(['amount']);

    $this->assertDatabaseCount('transfers', 0);
});

// ─── Fee: M-Pesa → Bank (PayBill) ────────────────────────────────────────────

it('charges M-Pesa PayBill fee when transferring from mpesa to bank', function () {
    $user = User::factory()->create();

    $mpesa = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 50000, 'initial_balance' => 50000, 'is_active' => true]);
    $bank  = Account::factory()->create(['user_id' => $user->id, 'type' => 'bank',  'current_balance' => 0,     'initial_balance' => 0,     'is_active' => true]);

    $this->actingAs($user)
        ->post(route('accounts.transferPost'), [
            'from_account_id' => $mpesa->id,
            'to_account_id'   => $bank->id,
            'amount'          => 2000,
            'date'            => now()->toDateString(),
        ])
        ->assertRedirect(route('accounts.index'))
        ->assertSessionHas('success');

    // 2000 falls in the 1501–2500 tier → KES 20 PayBill fee
    $this->assertDatabaseHas('transactions', [
        'account_id'         => $mpesa->id,
        'amount'             => 20,
        'is_transaction_fee' => true,
    ]);
});

it('charges no PayBill fee for Airtel Money transfers to bank', function () {
    $user = User::factory()->create();

    $airtel = Account::factory()->create(['user_id' => $user->id, 'type' => 'airtel_money', 'current_balance' => 50000, 'initial_balance' => 50000, 'is_active' => true]);
    $bank   = Account::factory()->create(['user_id' => $user->id, 'type' => 'bank',         'current_balance' => 0,     'initial_balance' => 0,     'is_active' => true]);

    $this->actingAs($user)
        ->post(route('accounts.transferPost'), [
            'from_account_id' => $airtel->id,
            'to_account_id'   => $bank->id,
            'amount'          => 5000,
            'date'            => now()->toDateString(),
        ])
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('transactions', [
        'account_id'         => $airtel->id,
        'is_transaction_fee' => true,
    ]);
});

// ─── Fee: Bank → Cash (ATM) ───────────────────────────────────────────────────

it('charges ATM fee (KES 37.95) when transferring from bank to cash', function () {
    $user = User::factory()->create();

    $bank = Account::factory()->create(['user_id' => $user->id, 'type' => 'bank', 'current_balance' => 50000, 'initial_balance' => 50000, 'is_active' => true]);
    $cash = Account::factory()->create(['user_id' => $user->id, 'type' => 'cash', 'current_balance' => 0,     'initial_balance' => 0,     'is_active' => true]);

    $this->actingAs($user)
        ->post(route('accounts.transferPost'), [
            'from_account_id' => $bank->id,
            'to_account_id'   => $cash->id,
            'amount'          => 5000,
            'date'            => now()->toDateString(),
        ])
        ->assertSessionHas('success');

    $this->assertDatabaseHas('transactions', [
        'account_id'         => $bank->id,
        'amount'             => '37.95',
        'is_transaction_fee' => true,
    ]);
});

it('deducts fee from source balance in the insufficient-balance check', function () {
    $user = User::factory()->create();

    // ATM fee is 37.95; balance of 5037 covers 5000 + 37.95 (rounds up),
    // but balance of 5036 does not
    $bank = Account::factory()->create(['user_id' => $user->id, 'type' => 'bank', 'current_balance' => 5036, 'initial_balance' => 5036, 'is_active' => true]);
    $cash = Account::factory()->create(['user_id' => $user->id, 'type' => 'cash', 'current_balance' => 0,    'is_active' => true]);

    $this->actingAs($user)
        ->post(route('accounts.transferPost'), [
            'from_account_id' => $bank->id,
            'to_account_id'   => $cash->id,
            'amount'          => 5000,
            'date'            => now()->toDateString(),
        ])
        ->assertSessionHasErrors(['amount']);

    $this->assertDatabaseCount('transfers', 0);
});

// ─── Fee: fee creates "Transaction Fees" category automatically ───────────────

it('auto-creates the Transaction Fees category when a fee is charged', function () {
    $user = User::factory()->create();

    $mpesa = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 10000, 'initial_balance' => 10000, 'is_active' => true]);
    $cash  = Account::factory()->create(['user_id' => $user->id, 'type' => 'cash',  'current_balance' => 0,     'is_active' => true]);

    $this->actingAs($user)
        ->post(route('accounts.transferPost'), [
            'from_account_id' => $mpesa->id,
            'to_account_id'   => $cash->id,
            'amount'          => 500,
            'date'            => now()->toDateString(),
        ]);

    $this->assertDatabaseHas('categories', [
        'user_id' => $user->id,
        'name'    => 'Transaction Fees',
        'type'    => 'expense',
    ]);
});

it('success message includes fee details when a fee is charged', function () {
    $user = User::factory()->create();

    $mpesa = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 10000, 'initial_balance' => 10000, 'is_active' => true]);
    $cash  = Account::factory()->create(['user_id' => $user->id, 'type' => 'cash',  'current_balance' => 0,     'is_active' => true]);

    $response = $this->actingAs($user)
        ->post(route('accounts.transferPost'), [
            'from_account_id' => $mpesa->id,
            'to_account_id'   => $cash->id,
            'amount'          => 500,
            'date'            => now()->toDateString(),
        ]);

    $successMsg = session('success');
    expect($successMsg)->toContain('fee');
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

it('validates required fields on transfer post', function () {
    $user = User::factory()->create();
    Account::factory()->count(2)->create(['user_id' => $user->id, 'is_active' => true]);

    $this->actingAs($user)
        ->post(route('accounts.transferPost'), [])
        ->assertSessionHasErrors(['from_account_id', 'to_account_id', 'amount', 'date']);
});

it('redirects post transfer when user has fewer than 2 accounts', function () {
    $user = User::factory()->create();
    Account::factory()->create(['user_id' => $user->id, 'is_active' => true]);

    $this->actingAs($user)
        ->post(route('accounts.transferPost'), [
            'from_account_id' => 1,
            'to_account_id'   => 2,
            'amount'          => 100,
            'date'            => now()->toDateString(),
        ])
        ->assertRedirect(route('accounts.index'))
        ->assertSessionHas('error');
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

it('blocks all system-reserved categories on top-up', function (string $catName) {
    $user     = User::factory()->create();
    $account  = Account::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create([
        'user_id'   => $user->id,
        'name'      => $catName,
        'type'      => 'expense',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('accounts.topup.store', $account), [
            'amount'      => 500,
            'category_id' => $category->id,
            'date'        => now()->toDateString(),
        ])
        ->assertSessionHas('error');
})->with(['Loan Receipt', 'Loan Repayment', 'Excise Duty', 'Loan Fees Refund', 'Facility Fee Refund', 'Balance Adjustment', 'Rolling Funds']);

it('blocks top-up with a category owned by another user', function () {
    $user     = User::factory()->create();
    $other    = User::factory()->create();
    $account  = Account::factory()->create(['user_id' => $user->id]);
    $category = Category::factory()->create(['user_id' => $other->id, 'type' => 'income', 'is_active' => true]);

    $this->actingAs($user)
        ->post(route('accounts.topup.store', $account), [
            'amount'      => 1000,
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

it('only allows Salary top-up for bank accounts', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'bank']);

    $parent    = Category::factory()->create(['user_id' => $user->id, 'name' => 'Income', 'type' => 'income', 'parent_id' => null, 'is_active' => true]);
    $nonSalary = Category::factory()->create(['user_id' => $user->id, 'type' => 'income', 'name' => 'Bonus',  'parent_id' => $parent->id, 'is_active' => true]);

    $this->actingAs($user)
        ->post(route('accounts.topup.store', $account), [
            'amount'      => 10000,
            'category_id' => $nonSalary->id,
            'date'        => now()->toDateString(),
        ])
        ->assertSessionHas('error');
});

it('uses a default description when none is provided for a top-up', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'name' => 'My Mpesa', 'current_balance' => 0, 'initial_balance' => 0]);

    $parent = Category::factory()->create(['user_id' => $user->id, 'name' => 'Income', 'type' => 'income', 'parent_id' => null, 'is_active' => true]);
    $cat    = Category::factory()->create(['user_id' => $user->id, 'type' => 'income', 'name' => 'Gifts', 'parent_id' => $parent->id, 'is_active' => true]);

    $this->actingAs($user)
        ->post(route('accounts.topup.store', $account), [
            'amount'      => 500,
            'category_id' => $cat->id,
            'date'        => now()->toDateString(),
            // no description
        ]);

    $this->assertDatabaseHas('transactions', [
        'account_id'  => $account->id,
        'description' => 'Top-up to My Mpesa',
    ]);
});

it('uses "Deposit to" default description for savings accounts', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'savings', 'name' => 'Emergency Fund', 'current_balance' => 0, 'initial_balance' => 0]);

    $parent = Category::factory()->create(['user_id' => $user->id, 'name' => 'Income', 'type' => 'income', 'parent_id' => null, 'is_active' => true]);
    $cat    = Category::factory()->create(['user_id' => $user->id, 'type' => 'income', 'name' => 'Gifts', 'parent_id' => $parent->id, 'is_active' => true]);

    $this->actingAs($user)
        ->post(route('accounts.topup.store', $account), [
            'amount'      => 2000,
            'category_id' => $cat->id,
            'date'        => now()->toDateString(),
        ]);

    $this->assertDatabaseHas('transactions', [
        'account_id'  => $account->id,
        'description' => 'Deposit to Emergency Fund',
    ]);
});

it('validates required fields on top-up store', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('accounts.topup.store', $account), [])
        ->assertSessionHasErrors(['amount', 'category_id', 'date']);
});

it('uses period_date when provided on top-up', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 0, 'initial_balance' => 0]);

    $parent = Category::factory()->create(['user_id' => $user->id, 'name' => 'Income', 'type' => 'income', 'parent_id' => null, 'is_active' => true]);
    $cat    = Category::factory()->create(['user_id' => $user->id, 'type' => 'income', 'name' => 'Freelance', 'parent_id' => $parent->id, 'is_active' => true]);

    $this->actingAs($user)
        ->post(route('accounts.topup.store', $account), [
            'amount'      => 1000,
            'category_id' => $cat->id,
            'date'        => '2025-04-01',
            'period_date' => '2025-03-31',
        ]);

    $this->assertDatabaseHas('transactions', [
        'account_id'  => $account->id,
        'period_date' => '2025-03-31',
    ]);
});

// ─── Sacco Dividends top-up ───────────────────────────────────────────────────

function saccoSetup(): array
{
    $user    = User::factory()->create();

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

// ─── Top-up: account type category filtering ──────────────────────────────────

it('top-up form for mpesa excludes Salary category', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'is_active' => true]);

    $parent = Category::factory()->create(['user_id' => $user->id, 'name' => 'Income', 'type' => 'income', 'parent_id' => null, 'is_active' => true]);
    Category::factory()->create(['user_id' => $user->id, 'type' => 'income', 'name' => 'Salary',    'parent_id' => $parent->id, 'is_active' => true]);
    Category::factory()->create(['user_id' => $user->id, 'type' => 'income', 'name' => 'Freelance', 'parent_id' => $parent->id, 'is_active' => true]);

    $response = $this->actingAs($user)->get(route('accounts.topup', $account));

    $categoryNames = $response->viewData('categories')->pluck('name');
    expect($categoryNames)->not->toContain('Salary')
        ->and($categoryNames)->toContain('Freelance');
});

it('top-up form for bank only shows Salary category', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'bank', 'is_active' => true]);

    $parent = Category::factory()->create(['user_id' => $user->id, 'name' => 'Income', 'type' => 'income', 'parent_id' => null, 'is_active' => true]);
    Category::factory()->create(['user_id' => $user->id, 'type' => 'income', 'name' => 'Salary',    'parent_id' => $parent->id, 'is_active' => true]);
    Category::factory()->create(['user_id' => $user->id, 'type' => 'income', 'name' => 'Freelance', 'parent_id' => $parent->id, 'is_active' => true]);

    $response = $this->actingAs($user)->get(route('accounts.topup', $account));

    $categoryNames = $response->viewData('categories')->pluck('name');
    expect($categoryNames)->toContain('Salary')
        ->and($categoryNames)->not->toContain('Freelance');
});

it('top-up form excludes categories with no parent (top-level)', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'is_active' => true]);

    Category::factory()->create(['user_id' => $user->id, 'name' => 'Income', 'type' => 'income', 'parent_id' => null, 'is_active' => true]);

    $response  = $this->actingAs($user)->get(route('accounts.topup', $account));
    $catNames  = $response->viewData('categories')->pluck('name');

    expect($catNames)->not->toContain('Income');
});
