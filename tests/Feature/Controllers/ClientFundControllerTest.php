<?php

namespace Tests\Feature\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\ClientFund;
use App\Models\ClientFundTransaction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeUser(): User
{
    return User::factory()->create();
}

function makeAccount(User $user, string $type = 'mpesa', float $balance = 10000): Account
{
    return Account::factory()->create([
        'user_id'         => $user->id,
        'type'            => $type,
        'current_balance' => $balance,
        'is_active'       => true,
    ]);
}

function makeClientFund(User $user, Account $account, array $overrides = []): ClientFund
{
    return ClientFund::factory()->create(array_merge([
        'user_id'         => $user->id,
        'account_id'      => $account->id,
        'client_name'     => 'Test Client',
        'type'            => 'commission',
        'amount_received' => 5000,
        'amount_spent'    => 0,
        'profit_amount'   => 0,
        'balance'         => 5000,
        'status'          => 'pending',
        'purpose'         => 'Test purpose',
        'received_date'   => now()->toDateString(),
    ], $overrides));
}

function makeExpenseCategory(User $user): Category
{
    return Category::factory()->create([
        'user_id' => $user->id,
        'type'    => 'expense',
        'name'    => 'Office Supplies',
    ]);
}

/**
 * Insert a ClientFundTransaction row directly — no factory needed.
 * The model uses $fillable so ::create() works fine.
 */
function makeCFT(ClientFund $fund, string $type, float $amount, ?int $transactionId = null): ClientFundTransaction
{
    return ClientFundTransaction::create([
        'client_fund_id' => $fund->id,
        'transaction_id' => $transactionId,
        'type'           => $type,
        'amount'         => $amount,
        'date'           => now()->toDateString(),
        'description'    => "{$type} entry",
    ]);
}

// ── store ─────────────────────────────────────────────────────────────────────

describe('store', function () {

    it('creates a client fund with a liability transaction', function () {
        $user    = makeUser();
        $account = makeAccount($user, 'mpesa');

        $this->actingAs($user)->post(route('client-funds.store'), [
            'client_name'     => 'Alice',
            'type'            => 'commission',
            'amount_received' => 3000,
            'account_id'      => $account->id,
            'purpose'         => 'Laptop purchase',
            'received_date'   => '2024-06-01',
        ])->assertRedirect();

        expect(ClientFund::where('user_id', $user->id)->count())->toBe(1);

        $fund = ClientFund::first();
        expect($fund->balance)->toEqual('3000.00')
            ->and($fund->status)->toBe('pending')
            ->and($fund->amount_spent)->toEqual('0.00');

        expect(
            Transaction::where('account_id', $account->id)->where('amount', 3000)->exists()
        )->toBeTrue();

        expect(
            ClientFundTransaction::where('client_fund_id', $fund->id)->where('type', 'receipt')->exists()
        )->toBeTrue();
    });

    it('rejects a cash account for client funds', function () {
        $user    = makeUser();
        $account = makeAccount($user, 'cash');

        $this->actingAs($user)->post(route('client-funds.store'), [
            'client_name'     => 'Bob',
            'type'            => 'commission',
            'amount_received' => 1000,
            'account_id'      => $account->id,
            'purpose'         => 'Supplies',
            'received_date'   => '2024-06-01',
        ])->assertSessionHasErrors('account_id');

        expect(ClientFund::count())->toBe(0);
    });

    it('rejects an account belonging to another user', function () {
        $user  = makeUser();
        $other = makeUser();
        $acct  = makeAccount($other, 'mpesa');

        $this->actingAs($user)->post(route('client-funds.store'), [
            'client_name'     => 'Eve',
            'type'            => 'commission',
            'amount_received' => 500,
            'account_id'      => $acct->id,
            'purpose'         => 'Theft attempt',
            'received_date'   => '2024-06-01',
        ])->assertSessionHasErrors('account_id');
    });

    it('requires all mandatory fields', function () {
        $user = makeUser();

        $this->actingAs($user)->post(route('client-funds.store'), [])
            ->assertSessionHasErrors(['client_name', 'type', 'amount_received', 'account_id', 'purpose', 'received_date']);
    });

    it('creates the Client Funds liability category when it does not exist', function () {
        $user    = makeUser();
        $account = makeAccount($user, 'bank');

        expect(Category::where('user_id', $user->id)->where('name', 'Client Funds')->exists())->toBeFalse();

        $this->actingAs($user)->post(route('client-funds.store'), [
            'client_name'     => 'Corp',
            'type'            => 'no_profit',
            'amount_received' => 2000,
            'account_id'      => $account->id,
            'purpose'         => 'Office',
            'received_date'   => '2024-06-01',
        ]);

        expect(Category::where('user_id', $user->id)->where('name', 'Client Funds')->exists())->toBeTrue();
    });
});

// ── recordExpense ─────────────────────────────────────────────────────────────
// Route: POST /client-funds/{clientFund}/expense  (adjust if your route differs)

describe('recordExpense', function () {

    it('records an expense and reduces the client fund balance', function () {
        $user     = makeUser();
        $account  = makeAccount($user, 'mpesa', 10000);
        $fund     = makeClientFund($user, $account, ['amount_received' => 5000, 'balance' => 5000]);
        $category = makeExpenseCategory($user);

        $this->actingAs($user)
            ->post("/client-funds/{$fund->id}/expense", [
                'amount'      => 1000,
                'description' => 'Bought supplies',
                'date'        => '2024-06-05',
                'category_id' => $category->id,
            ])->assertRedirect()->assertSessionHas('success');

        $fund->refresh();
        expect((float) $fund->amount_spent)->toBe(1000.0)
            ->and((float) $fund->balance)->toBe(4000.0)
            ->and($fund->status)->toBe('partial');
    });

    it('creates an expense Transaction and a CFT record', function () {
        $user     = makeUser();
        $account  = makeAccount($user, 'mpesa', 10000);
        $fund     = makeClientFund($user, $account);
        $category = makeExpenseCategory($user);

        $this->actingAs($user)
            ->post("/client-funds/{$fund->id}/expense", [
                'amount'      => 500,
                'description' => 'Stationery',
                'date'        => '2024-06-05',
                'category_id' => $category->id,
            ]);

        expect(
            Transaction::where('account_id', $account->id)
                ->where('amount', 500)
                ->where('category_id', $category->id)
                ->exists()
        )->toBeTrue();

        expect(
            ClientFundTransaction::where('client_fund_id', $fund->id)
                ->where('type', 'expense')
                ->where('amount', 500)
                ->exists()
        )->toBeTrue();
    });

    it('rejects an expense exceeding the fund balance', function () {
        $user     = makeUser();
        $account  = makeAccount($user, 'mpesa');
        $fund     = makeClientFund($user, $account, ['balance' => 200]);
        $category = makeExpenseCategory($user);

        $this->actingAs($user)
            ->post("/client-funds/{$fund->id}/expense", [
                'amount'      => 500,
                'description' => 'Too much',
                'date'        => '2024-06-05',
                'category_id' => $category->id,
            ])->assertSessionHasErrors('amount');
    });

    it('marks the fund as completed when the full balance is spent', function () {
        $user     = makeUser();
        $account  = makeAccount($user, 'mpesa', 10000);
        $fund     = makeClientFund($user, $account, ['amount_received' => 1000, 'balance' => 1000]);
        $category = makeExpenseCategory($user);

        $this->actingAs($user)
            ->post("/client-funds/{$fund->id}/expense", [
                'amount'      => 1000,
                'description' => 'Full spend',
                'date'        => '2024-06-05',
                'category_id' => $category->id,
            ]);

        expect($fund->fresh()->status)->toBe('completed');
    });

    it('prevents a different user from recording an expense', function () {
        $user     = makeUser();
        $other    = makeUser();
        $account  = makeAccount($user, 'mpesa');
        $fund     = makeClientFund($user, $account);
        $category = makeExpenseCategory($other);

        $this->actingAs($other)
            ->post("/client-funds/{$fund->id}/expense", [
                'amount'      => 100,
                'description' => 'Sneaky',
                'date'        => '2024-06-05',
                'category_id' => $category->id,
            ])->assertForbidden();
    });
});

// ── recordProfit ──────────────────────────────────────────────────────────────
// Route: POST /client-funds/{clientFund}/profit

describe('recordProfit', function () {

    it('records profit on a commission-type fund', function () {
        $user    = makeUser();
        $account = makeAccount($user, 'mpesa', 10000);
        $fund    = makeClientFund($user, $account, ['type' => 'commission', 'amount_received' => 5000, 'balance' => 5000]);

        Category::factory()->create(['user_id' => $user->id, 'name' => 'Client Funds', 'type' => 'liability']);
        Category::factory()->create(['user_id' => $user->id, 'name' => 'Income', 'type' => 'income', 'parent_id' => null]);

        $this->actingAs($user)
            ->post("/client-funds/{$fund->id}/profit", [
                'amount'      => 500,
                'description' => 'My commission',
                'date'        => '2024-06-10',
            ])->assertRedirect()->assertSessionHas('success');

        $fund->refresh();
        expect((float) $fund->profit_amount)->toBe(500.0)
            ->and((float) $fund->balance)->toBe(4500.0);
    });

    it('rejects profit on a no_profit fund', function () {
        $user    = makeUser();
        $account = makeAccount($user, 'mpesa');
        $fund    = makeClientFund($user, $account, ['type' => 'no_profit']);

        $this->actingAs($user)
            ->post("/client-funds/{$fund->id}/profit", [
                'amount' => 100,
                'date'   => '2024-06-10',
            ])->assertSessionHas('error');

        expect($fund->fresh()->profit_amount)->toEqual('0.00');
    });

    it('creates an income transaction and a liability-reduction transaction', function () {
        $user    = makeUser();
        $account = makeAccount($user, 'mpesa', 10000);
        $fund    = makeClientFund($user, $account, ['type' => 'commission', 'amount_received' => 5000, 'balance' => 5000]);

        Category::factory()->create(['user_id' => $user->id, 'name' => 'Client Funds', 'type' => 'liability']);
        Category::factory()->create(['user_id' => $user->id, 'name' => 'Income', 'type' => 'income', 'parent_id' => null]);

        $this->actingAs($user)
            ->post("/client-funds/{$fund->id}/profit", [
                'amount' => 300,
                'date'   => '2024-06-10',
            ]);

        // Positive income transaction
        expect(
            Transaction::where('account_id', $account->id)
                ->where('amount', 300)
                ->where('payment_method', 'Client Commission')
                ->exists()
        )->toBeTrue();

        // Negative liability-reduction transaction
        expect(
            Transaction::where('account_id', $account->id)
                ->where('amount', -300)
                ->where('payment_method', 'Client Commission')
                ->exists()
        )->toBeTrue();

        // CFT of type 'profit'
        expect(
            ClientFundTransaction::where('client_fund_id', $fund->id)
                ->where('type', 'profit')
                ->where('amount', 300)
                ->exists()
        )->toBeTrue();
    });

    it('prevents another user from recording profit', function () {
        $user    = makeUser();
        $other   = makeUser();
        $account = makeAccount($user, 'mpesa');
        $fund    = makeClientFund($user, $account, ['type' => 'commission']);

        $this->actingAs($other)
            ->post("/client-funds/{$fund->id}/profit", [
                'amount' => 100,
                'date'   => '2024-06-10',
            ])->assertForbidden();
    });
});

// ── complete ──────────────────────────────────────────────────────────────────
// Route: POST /client-funds/{clientFund}/complete

describe('complete', function () {

    it('marks a zero-balance fund as completed', function () {
        $user    = makeUser();
        $account = makeAccount($user, 'mpesa');
        $fund    = makeClientFund($user, $account, [
            'balance'         => 0,
            'amount_spent'    => 5000,
            'amount_received' => 5000,
            'status'          => 'partial',
        ]);

        $this->actingAs($user)
            ->post("/client-funds/{$fund->id}/complete")
            ->assertRedirect()->assertSessionHas('success');

        $fund->refresh();
        expect($fund->status)->toBe('completed')
            ->and($fund->completed_date)->not->toBeNull();
    });

    it('prevents completing a fund with a remaining balance', function () {
        $user    = makeUser();
        $account = makeAccount($user, 'mpesa');
        $fund    = makeClientFund($user, $account, ['balance' => 1000]);

        $this->actingAs($user)
            ->post("/client-funds/{$fund->id}/complete")
            ->assertSessionHas('error');

        expect($fund->fresh()->status)->not->toBe('completed');
    });

    it('prevents another user from completing the fund', function () {
        $user    = makeUser();
        $other   = makeUser();
        $account = makeAccount($user, 'mpesa');
        $fund    = makeClientFund($user, $account, ['balance' => 0]);

        $this->actingAs($other)
            ->post("/client-funds/{$fund->id}/complete")
            ->assertForbidden();
    });
});

// ── destroy ───────────────────────────────────────────────────────────────────

describe('destroy', function () {

    it('deletes a fund that has no expenses or profits', function () {
        $user    = makeUser();
        $account = makeAccount($user, 'mpesa', 10000);
        $fund    = makeClientFund($user, $account);

        makeCFT($fund, 'receipt', 5000);

        $this->actingAs($user)
            ->delete(route('client-funds.destroy', $fund))
            ->assertRedirect(route('client-funds.index'))
            ->assertSessionHas('success');

        expect(ClientFund::find($fund->id))->toBeNull();
    });

    it('blocks deletion when expenses exist', function () {
        $user    = makeUser();
        $account = makeAccount($user, 'mpesa');
        $fund    = makeClientFund($user, $account);

        makeCFT($fund, 'expense', 200);

        $this->actingAs($user)
            ->delete(route('client-funds.destroy', $fund))
            ->assertSessionHas('error');

        expect(ClientFund::find($fund->id))->not->toBeNull();
    });

    it('blocks deletion when profits exist', function () {
        $user    = makeUser();
        $account = makeAccount($user, 'mpesa');
        $fund    = makeClientFund($user, $account, ['type' => 'commission']);

        makeCFT($fund, 'profit', 100);

        $this->actingAs($user)
            ->delete(route('client-funds.destroy', $fund))
            ->assertSessionHas('error');

        expect(ClientFund::find($fund->id))->not->toBeNull();
    });

    it('prevents another user from deleting the fund', function () {
        $user    = makeUser();
        $other   = makeUser();
        $account = makeAccount($user, 'mpesa');
        $fund    = makeClientFund($user, $account);

        $this->actingAs($other)
            ->delete(route('client-funds.destroy', $fund))
            ->assertForbidden();
    });
});

// ── deleteExpense ─────────────────────────────────────────────────────────────
// Route: DELETE /client-funds/{clientFund}/expense/{transaction}

describe('deleteExpense', function () {

    it('reverses an expense and restores the fund balance', function () {
        $user    = makeUser();
        $account = makeAccount($user, 'mpesa', 10000);
        $fund    = makeClientFund($user, $account, [
            'amount_received' => 5000,
            'amount_spent'    => 1000,
            'balance'         => 4000,
            'status'          => 'partial',
        ]);

        $txn = makeCFT($fund, 'expense', 1000);

        $this->actingAs($user)
            ->delete("/client-funds/{$fund->id}/expense/{$txn->id}")
            ->assertRedirect()->assertSessionHas('success');

        $fund->refresh();
        expect((float) $fund->amount_spent)->toBe(0.0)
            ->and((float) $fund->balance)->toBe(5000.0);

        expect(ClientFundTransaction::find($txn->id))->toBeNull();
    });

    it('rejects deletion if the transaction type is not expense', function () {
        $user    = makeUser();
        $account = makeAccount($user, 'mpesa');
        $fund    = makeClientFund($user, $account);
        $txn     = makeCFT($fund, 'receipt', 500);

        $this->actingAs($user)
            ->delete("/client-funds/{$fund->id}/expense/{$txn->id}")
            ->assertSessionHas('error');

        expect(ClientFundTransaction::find($txn->id))->not->toBeNull();
    });

    it('prevents another user from deleting the expense', function () {
        $user    = makeUser();
        $other   = makeUser();
        $account = makeAccount($user, 'mpesa');
        $fund    = makeClientFund($user, $account);
        $txn     = makeCFT($fund, 'expense', 200);

        $this->actingAs($other)
            ->delete("/client-funds/{$fund->id}/expense/{$txn->id}")
            ->assertForbidden();
    });
});

// ── deleteProfit ──────────────────────────────────────────────────────────────
// Route: DELETE /client-funds/{clientFund}/profit/{transaction}

describe('deleteProfit', function () {

    it('reverses a profit and restores the fund balance', function () {
        $user    = makeUser();
        $account = makeAccount($user, 'mpesa', 10000);
        $fund    = makeClientFund($user, $account, [
            'type'            => 'commission',
            'amount_received' => 5000,
            'profit_amount'   => 500,
            'balance'         => 4500,
        ]);

        $linkedTxn = Transaction::factory()->create([
            'user_id'    => $user->id,
            'account_id' => $account->id,
            'amount'     => -500,
        ]);

        $profitCFT = makeCFT($fund, 'profit', 500, $linkedTxn->id);

        $this->actingAs($user)
            ->delete("/client-funds/{$fund->id}/profit/{$profitCFT->id}")
            ->assertRedirect()->assertSessionHas('success');

        $fund->refresh();
        expect((float) $fund->profit_amount)->toBe(0.0)
            ->and((float) $fund->balance)->toBe(5000.0);

        expect(ClientFundTransaction::find($profitCFT->id))->toBeNull();
        expect(Transaction::find($linkedTxn->id))->toBeNull();
    });

    it('rejects deletion if the transaction type is not profit', function () {
        $user    = makeUser();
        $account = makeAccount($user, 'mpesa');
        $fund    = makeClientFund($user, $account, ['type' => 'commission']);
        $txn     = makeCFT($fund, 'receipt', 1000);

        $this->actingAs($user)
            ->delete("/client-funds/{$fund->id}/profit/{$txn->id}")
            ->assertSessionHas('error');

        expect(ClientFundTransaction::find($txn->id))->not->toBeNull();
    });

    it('prevents another user from deleting the profit', function () {
        $user    = makeUser();
        $other   = makeUser();
        $account = makeAccount($user, 'mpesa');
        $fund    = makeClientFund($user, $account, ['type' => 'commission']);
        $txn     = makeCFT($fund, 'profit', 200);

        $this->actingAs($other)
            ->delete("/client-funds/{$fund->id}/profit/{$txn->id}")
            ->assertForbidden();
    });
});

// ── update ────────────────────────────────────────────────────────────────────

describe('update', function () {

    it('updates editable fields without touching amounts', function () {
        $user    = makeUser();
        $account = makeAccount($user, 'mpesa');
        $fund    = makeClientFund($user, $account, ['client_name' => 'Old Name', 'purpose' => 'Old purpose']);

        $this->actingAs($user)->put(route('client-funds.update', $fund), [
            'client_name' => 'New Name',
            'purpose'     => 'New purpose',
            'notes'       => 'Some notes',
        ])->assertRedirect(route('client-funds.show', $fund));

        $fund->refresh();
        expect($fund->client_name)->toBe('New Name')
            ->and($fund->purpose)->toBe('New purpose')
            ->and((float) $fund->amount_received)->toBe(5000.0);
    });

    it('prevents another user from updating the fund', function () {
        $user    = makeUser();
        $other   = makeUser();
        $account = makeAccount($user, 'mpesa');
        $fund    = makeClientFund($user, $account);

        $this->actingAs($other)->put(route('client-funds.update', $fund), [
            'client_name' => 'Hacker',
            'purpose'     => 'Steal',
        ])->assertForbidden();
    });
});

// ── ClientFund model: updateBalance ──────────────────────────────────────────

describe('ClientFund::updateBalance', function () {

    it('sets status to partial when some amount is spent', function () {
        $user    = makeUser();
        $account = makeAccount($user);
        $fund    = makeClientFund($user, $account, [
            'amount_received' => 1000,
            'amount_spent'    => 400,
            'profit_amount'   => 0,
            'balance'         => 1000,
            'status'          => 'pending',
        ]);

        $fund->updateBalance();

        expect($fund->fresh()->status)->toBe('partial')
            ->and((float) $fund->fresh()->balance)->toBe(600.0);
    });

    it('sets status to completed when balance reaches 0', function () {
        $user    = makeUser();
        $account = makeAccount($user);
        $fund    = makeClientFund($user, $account, [
            'amount_received' => 1000,
            'amount_spent'    => 700,
            'profit_amount'   => 300,
            'balance'         => 1000,
            'status'          => 'partial',
        ]);

        $fund->updateBalance();

        expect($fund->fresh()->status)->toBe('completed')
            ->and((float) $fund->fresh()->balance)->toBe(0.0)
            ->and($fund->fresh()->completed_date)->not->toBeNull();
    });

    it('computes balance as received minus spent minus profit', function () {
        $user    = makeUser();
        $account = makeAccount($user);
        $fund    = makeClientFund($user, $account, [
            'amount_received' => 5000,
            'amount_spent'    => 1500,
            'profit_amount'   => 200,
            'balance'         => 9999,
        ]);

        $fund->updateBalance();

        expect((float) $fund->fresh()->balance)->toBe(3300.0);
    });
});
