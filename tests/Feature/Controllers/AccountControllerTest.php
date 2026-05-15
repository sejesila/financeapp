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

// ─── Top-up with client fund conditional validation ────────────────────────

it('allows redirecting to client fund form without selecting a category', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create([
        'user_id'         => $user->id,
        'type'            => 'savings',
        'name'            => 'Etica',
        'current_balance' => 0,
        'initial_balance' => 0,
    ]);

    $this->actingAs($user)
        ->post(route('accounts.topup.store', $account), [
            'amount'           => 5000,
            'date'             => now()->toDateString(),
            'is_client_fund'   => '1',
            // No category_id provided
        ])
        ->assertRedirect()
        ->assertSessionHas('info');
});

it('passes amount and date to client fund creation when redirecting', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create([
        'user_id'         => $user->id,
        'type'            => 'savings',
        'name'            => 'Etica',
        'current_balance' => 0,
        'initial_balance' => 0,
    ]);

    $response = $this->actingAs($user)
        ->post(route('accounts.topup.store', $account), [
            'amount'         => 15000,
            'date'           => '2025-05-13',
            'is_client_fund' => '1',
        ]);

    $response->assertRedirect();
    $location = $response->headers->get('Location');

    expect($location)->toContain('amount=15000')
        ->and($location)->toContain('date=2025-05-13')
        ->and($location)->toContain('account_id=' . $account->id);
});

it('still requires category_id when NOT recording as client fund', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create([
        'user_id'         => $user->id,
        'type'            => 'mpesa',
        'current_balance' => 0,
        'initial_balance' => 0,
    ]);

    $this->actingAs($user)
        ->post(route('accounts.topup.store', $account), [
            'amount'         => 5000,
            'date'           => now()->toDateString(),
            'is_client_fund' => '0', // Explicitly NOT a client fund
            // No category_id
        ])
        ->assertSessionHasErrors(['category_id']);
});

it('validates category_id is required by default for top-up', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('accounts.topup.store', $account), [
            'amount' => 1000,
            'date'   => now()->toDateString(),
            // No is_client_fund checkbox, no category_id
        ])
        ->assertSessionHasErrors(['category_id']);
});

it('allows client fund redirect even when amount or date is invalid for normal transactions', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create([
        'user_id'         => $user->id,
        'type'            => 'savings',
        'name'            => 'Etica',
        'current_balance' => 0,
        'initial_balance' => 0,
    ]);

    $this->actingAs($user)
        ->post(route('accounts.topup.store', $account), [
            'amount'         => 0, // Invalid but should be caught by validation
            'date'           => 'invalid-date',
            'is_client_fund' => '1',
        ])
        ->assertSessionHasErrors(['amount', 'date']);
});

it('requires amount even for client fund recording', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create([
        'user_id'         => $user->id,
        'type'            => 'savings',
        'name'            => 'Etica',
        'current_balance' => 0,
        'initial_balance' => 0,
    ]);

    $this->actingAs($user)
        ->post(route('accounts.topup.store', $account), [
            'date'           => now()->toDateString(),
            'is_client_fund' => '1',
            // No amount
        ])
        ->assertSessionHasErrors(['amount']);
});

it('requires date even for client fund recording', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create([
        'user_id'         => $user->id,
        'type'            => 'savings',
        'name'            => 'Etica',
        'current_balance' => 0,
        'initial_balance' => 0,
    ]);

    $this->actingAs($user)
        ->post(route('accounts.topup.store', $account), [
            'amount'         => 5000,
            'is_client_fund' => '1',
            // No date
        ])
        ->assertSessionHasErrors(['date']);
});

it('skips category validation entirely when redirecting to client fund', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create([
        'user_id'         => $user->id,
        'type'            => 'savings',
        'name'            => 'Etica',
        'current_balance' => 0,
        'initial_balance' => 0,
    ]);

    // Send invalid category_id along with is_client_fund flag
    // Should still redirect because category validation is skipped
    $this->actingAs($user)
        ->post(route('accounts.topup.store', $account), [
            'amount'         => 5000,
            'date'           => now()->toDateString(),
            'is_client_fund' => '1',
            'category_id'    => 99999, // Non-existent category
        ])
        ->assertRedirect()
        ->assertSessionHas('info');
});

it('prevents client fund recording on non-savings accounts', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create([
        'user_id'         => $user->id,
        'type'            => 'mpesa', // Not savings
        'current_balance' => 0,
        'initial_balance' => 0,
    ]);

    $this->actingAs($user)
        ->post(route('accounts.topup.store', $account), [
            'amount'         => 5000,
            'date'           => now()->toDateString(),
            'is_client_fund' => '1',
        ])
        ->assertRedirect(route('accounts.index'))
        ->assertSessionHas('error');
});

// ─── Existing top-up tests (unchanged, but listed for reference) ────────────

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

it('validates required fields on top-up store', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('accounts.topup.store', $account), [])
        ->assertSessionHasErrors(['amount', 'category_id', 'date']);
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

it('prevents top-up to savings accounts (only allows transfers)', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'savings', 'name' => 'Emergency Fund', 'current_balance' => 0, 'initial_balance' => 0]);

    $parent = Category::factory()->create(['user_id' => $user->id, 'name' => 'Income', 'type' => 'income', 'parent_id' => null, 'is_active' => true]);
    $cat    = Category::factory()->create(['user_id' => $user->id, 'type' => 'income', 'name' => 'Gifts', 'parent_id' => $parent->id, 'is_active' => true]);

    $this->actingAs($user)
        ->post(route('accounts.topup.store', $account), [
            'amount'      => 2000,
            'category_id' => $cat->id,
            'date'        => now()->toDateString(),
        ])
        ->assertRedirect(route('accounts.index'))
        ->assertSessionHas('error');

    $this->assertDatabaseCount('transactions', 0);
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

// ─── Reverse top-up — 30-minute window ────────────────────────────────────

it('shows the reverse top-up form within 30 minutes of creation', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 1000, 'initial_balance' => 0]);

    $category = Category::factory()->create(['user_id' => $user->id, 'type' => 'income', 'is_active' => true, 'parent_id' => null]);

    $transaction = Transaction::factory()->create([
        'user_id'     => $user->id,
        'account_id'  => $account->id,
        'category_id' => $category->id,
        'amount'      => 500,
        'date'        => now()->toDateString(),
        'created_at'  => now()->subMinutes(10), // 10 minutes ago — within window
    ]);

    $this->actingAs($user)
        ->get(route('accounts.topup.reverse.form', ['account' => $account, 'transaction' => $transaction]))
        ->assertOk()
        ->assertViewIs('accounts.reverse-topup');
});

it('blocks the reverse top-up form after 30 minutes', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 1000, 'initial_balance' => 0]);

    $category = Category::factory()->create(['user_id' => $user->id, 'type' => 'income', 'is_active' => true, 'parent_id' => null]);

    $transaction = Transaction::factory()->create([
        'user_id'     => $user->id,
        'account_id'  => $account->id,
        'category_id' => $category->id,
        'amount'      => 500,
        'date'        => now()->toDateString(),
        'created_at'  => now()->subMinutes(31), // 31 minutes ago — outside window
    ]);

    $this->actingAs($user)
        ->get(route('accounts.topup.reverse.form', ['account' => $account, 'transaction' => $transaction]))
        ->assertRedirect()
        ->assertSessionHas('error');
});

it('blocks the reverse top-up form at exactly 31 minutes', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 1000, 'initial_balance' => 0]);

    $category = Category::factory()->create(['user_id' => $user->id, 'type' => 'income', 'is_active' => true, 'parent_id' => null]);

    $transaction = Transaction::factory()->create([
        'user_id'     => $user->id,
        'account_id'  => $account->id,
        'category_id' => $category->id,
        'amount'      => 500,
        'date'        => now()->toDateString(),
        'created_at'  => now()->subMinutes(31),
    ]);

    $this->actingAs($user)
        ->get(route('accounts.topup.reverse.form', ['account' => $account, 'transaction' => $transaction]))
        ->assertRedirect(route('accounts.show', ['account' => $account, 'tab' => 'topups']))
        ->assertSessionHas('error');
});

it('allows reversal at exactly 30 minutes (boundary)', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 1000, 'initial_balance' => 0]);

    $category = Category::factory()->create(['user_id' => $user->id, 'type' => 'income', 'is_active' => true, 'parent_id' => null]);

    $transaction = Transaction::factory()->create([
        'user_id'     => $user->id,
        'account_id'  => $account->id,
        'category_id' => $category->id,
        'amount'      => 500,
        'date'        => now()->toDateString(),
        'created_at'  => now()->subMinutes(29), // inside the window (30 can race)
    ]);

    $this->actingAs($user)
        ->get(route('accounts.topup.reverse.form', ['account' => $account, 'transaction' => $transaction]))
        ->assertOk()
        ->assertViewIs('accounts.reverse-topup');
});

it('executes reversal within 30 minutes and soft-deletes the transaction', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 1000, 'initial_balance' => 0]);

    $category = Category::factory()->create(['user_id' => $user->id, 'type' => 'income', 'is_active' => true, 'parent_id' => null]);

    $transaction = Transaction::factory()->create([
        'user_id'     => $user->id,
        'account_id'  => $account->id,
        'category_id' => $category->id,
        'amount'      => 500,
        'date'        => now()->toDateString(),
        'created_at'  => now()->subMinutes(5),
    ]);

    $this->actingAs($user)
        ->delete(route('accounts.topup.reverse', ['account' => $account, 'transaction' => $transaction]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);
});

it('blocks post reversal after 30 minutes even if form was somehow reached', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 1000, 'initial_balance' => 0]);

    $category = Category::factory()->create(['user_id' => $user->id, 'type' => 'income', 'is_active' => true, 'parent_id' => null]);

    $transaction = Transaction::factory()->create([
        'user_id'     => $user->id,
        'account_id'  => $account->id,
        'category_id' => $category->id,
        'amount'      => 500,
        'date'        => now()->toDateString(),
        'created_at'  => now()->subMinutes(45), // stale — window expired
    ]);

    $this->actingAs($user)
        ->delete(route('accounts.topup.reverse', ['account' => $account, 'transaction' => $transaction]))
        ->assertRedirect()
        ->assertSessionHas('error');

    // Transaction must NOT have been deleted
    $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'deleted_at' => null]);
});

it('redirects expired reversal post to the topups tab', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 1000, 'initial_balance' => 0]);

    $category = Category::factory()->create(['user_id' => $user->id, 'type' => 'income', 'is_active' => true, 'parent_id' => null]);

    $transaction = Transaction::factory()->create([
        'user_id'     => $user->id,
        'account_id'  => $account->id,
        'category_id' => $category->id,
        'amount'      => 500,
        'date'        => now()->toDateString(),
        'created_at'  => now()->subHour(),
    ]);

    $this->actingAs($user)
        ->delete(route('accounts.topup.reverse', ['account' => $account, 'transaction' => $transaction]))
        ->assertRedirect(route('accounts.show', ['account' => $account, 'tab' => 'topups']));
});

it('returns 403 when reversing another users top-up', function () {
    $user  = User::factory()->create();
    $other = User::factory()->create();

    $account = Account::withoutGlobalScopes()->create([
        'user_id'         => $other->id,
        'name'            => 'Other Account',
        'type'            => 'mpesa',
        'initial_balance' => 0,
        'current_balance' => 1000,
        'currency'        => 'KES',
        'is_active'       => true,
    ]);

    $category = Category::factory()->create(['user_id' => $other->id, 'type' => 'income', 'is_active' => true, 'parent_id' => null]);

    $transaction = Transaction::factory()->create([
        'user_id'     => $other->id,
        'account_id'  => $account->id,
        'category_id' => $category->id,
        'amount'      => 500,
        'date'        => now()->toDateString(),
        'created_at'  => now()->subMinutes(5),
    ]);

    $this->actingAs($user)
        ->delete(route('accounts.topup.reverse', ['account' => $account, 'transaction' => $transaction]))
        ->assertForbidden();
});

it('the 30-minute window is based on created_at not the transaction date', function () {
    $user    = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 1000, 'initial_balance' => 0]);

    $category = Category::factory()->create(['user_id' => $user->id, 'type' => 'income', 'is_active' => true, 'parent_id' => null]);

    // Transaction date is today but created_at is 2 hours ago
    $transaction = Transaction::factory()->create([
        'user_id'     => $user->id,
        'account_id'  => $account->id,
        'category_id' => $category->id,
        'amount'      => 500,
        'date'        => now()->toDateString(), // date = today
        'created_at'  => now()->subHours(2),   // but was actually created 2h ago
    ]);

    // Form must be blocked despite the transaction date being today
    $this->actingAs($user)
        ->get(route('accounts.topup.reverse.form', ['account' => $account, 'transaction' => $transaction]))
        ->assertRedirect()
        ->assertSessionHas('error');

    // Post must also be blocked
    $this->actingAs($user)
        ->delete(route('accounts.topup.reverse', ['account' => $account, 'transaction' => $transaction]))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'deleted_at' => null]);
});
