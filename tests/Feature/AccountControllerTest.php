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

uses( RefreshDatabase::class);

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

    // Use withoutGlobalScopes so the scope doesn't interfere with our lookup
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

    // Fetch the account bypassing the global scope so route model binding gets a slug to resolve
    $account = Account::withoutGlobalScopes()->create([
        'user_id'         => $other->id,
        'name'            => 'Other Account',
        'type'            => 'cash',
        'initial_balance' => 0,
        'current_balance' => 0,
        'currency'        => 'KES',
        'is_active'       => true,
    ]);

    // The controller resolves by slug; acting as $user means the global scope
    // hides $other's account → 404. The controller checks user_id and aborts 403.
    // We must hit the route with the slug directly — Laravel's route model binding
    // uses withoutGlobalScopes internally when resolving implicit bindings,
    // so the account IS found and the controller's manual check fires → 403.
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
            'remove_logo' => '1',   // send as string "1" as a form would
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
        ->get(route('accounts.transfer'))          // correct route name
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
        ->post(route('accounts.transferPost'), [   // correct route name
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
        ->post(route('accounts.transferPost'), [   // correct route name
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
        ->post(route('accounts.transferPost'), [   // correct route name
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
        ->post(route('accounts.transferPost'), [   // correct route name
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
        ->post(route('accounts.transferPost'), [   // correct route name
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
        ->post(route('accounts.topup.store', $account), [  // correct route name
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
        ->post(route('accounts.topup.store', $account), [  // correct route name
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
        ->post(route('accounts.topup.store', $account), [  // correct route name
            'amount'      => 1000,
            'category_id' => $cat->id,
            'date'        => now()->toDateString(),
        ])
        ->assertForbidden();
});
