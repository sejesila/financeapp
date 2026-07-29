<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\LoanGiven;
use App\Models\LoanGivenPayment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses( RefreshDatabase::class);

// ─── Store (disbursement) ──────────────────────────────────────────────────

describe('Creating a loan given', function () {

    it('creates a loan and a matching Friend Loan Given expense transaction', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create([
            'user_id' => $user->id,
            'type' => 'mpesa',
            'current_balance' => 5000,
            'initial_balance' => 5000,
        ]);

        $this->actingAs($user)
            ->post(route('loans-given.store'), [
                'borrower_name'    => 'John Doe',
                'account_id'       => $account->id,
                'principal_amount' => 1000,
                'disbursed_date'   => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('loans_given', [
            'user_id'          => $user->id,
            'borrower_name'    => 'John Doe',
            'principal_amount' => 1000,
            'balance'          => 1000,
            'status'           => 'active',
        ]);

        $this->assertDatabaseHas('transactions', [
            'account_id'  => $account->id,
            'amount'      => 1000,
            'type'        => 'expense',
            'description' => 'Loan disbursed to John Doe',
        ]);

        $category = Category::where('user_id', $user->id)->where('name', 'Friend Loan Given')->first();
        expect($category)->not->toBeNull()
            ->and($category->type)->toBe('expense');
    });

    it('reuses an existing Friend Loan Given category instead of creating a duplicate', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 5000]);

        $existing = Category::factory()->create([
            'user_id'   => $user->id,
            'name'      => 'Friend Loan Given',
            'type'      => 'expense',
            'parent_id' => null,
            'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('loans-given.store'), [
            'borrower_name'    => 'Jane Doe',
            'account_id'       => $account->id,
            'principal_amount' => 500,
            'disbursed_date'   => now()->toDateString(),
        ]);

        expect(Category::where('user_id', $user->id)->where('name', 'Friend Loan Given')->count())->toBe(1);

        $this->assertDatabaseHas('transactions', [
            'category_id' => $existing->id,
            'amount'      => 500,
        ]);
    });

    it('rejects a loan larger than the account balance', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 100]);

        $this->actingAs($user)
            ->post(route('loans-given.store'), [
                'borrower_name'    => 'John Doe',
                'account_id'       => $account->id,
                'principal_amount' => 500,
                'disbursed_date'   => now()->toDateString(),
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('loans_given', 0);
        $this->assertDatabaseCount('transactions', 0);
    });

    it('defaults due_date to 30 days after disbursement when not provided', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 5000]);
        $disbursed = '2026-01-01';

        $this->actingAs($user)->post(route('loans-given.store'), [
            'borrower_name'    => 'John Doe',
            'account_id'       => $account->id,
            'principal_amount' => 1000,
            'disbursed_date'   => $disbursed,
        ]);

        $this->assertDatabaseHas('loans_given', [
            'borrower_name' => 'John Doe',
            'due_date'      => '2026-01-31',
        ]);
    });

    it('requires borrower_name, account_id, principal_amount and disbursed_date', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('loans-given.store'), [])
            ->assertSessionHasErrors(['borrower_name', 'account_id', 'principal_amount', 'disbursed_date']);
    });

    it('does not create the loan or transaction when the account belongs to another user', function () {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $account = Account::withoutGlobalScopes()->create([
            'user_id' => $other->id, 'name' => 'Other', 'type' => 'cash',
            'initial_balance' => 5000, 'current_balance' => 5000,
            'currency' => 'KES', 'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('loans-given.store'), [
                'borrower_name'    => 'John Doe',
                'account_id'       => $account->id,
                'principal_amount' => 1000,
                'disbursed_date'   => now()->toDateString(),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('loans_given', 0);
    });
});

// ─── Recording payments ─────────────────────────────────────────────────────

describe('Recording loan repayments', function () {

    it('records a partial payment without closing the loan', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 5000]);
        $loan = LoanGiven::factory()->create([
            'user_id' => $user->id, 'account_id' => $account->id,
            'principal_amount' => 1000, 'balance' => 1000, 'amount_paid' => 0,
        ]);

        $this->actingAs($user)
            ->post(route('loans-given.payment', $loan), [
                'payment_account_id' => $account->id,
                'payment_amount'     => 400,
                'payment_date'       => now()->toDateString(),
            ])
            ->assertRedirect();

        $loan->refresh();
        expect((float) $loan->amount_paid)->toBe(400.0)
            ->and((float) $loan->balance)->toBe(600.0)
            ->and($loan->status)->toBe('active');

        $this->assertDatabaseHas('transactions', [
            'account_id'  => $account->id,
            'amount'      => 400,
            'type'        => 'income',
            'description' => "Loan repayment from {$loan->borrower_name}",
        ]);

        $this->assertDatabaseHas('loan_given_payments', [
            'loan_given_id' => $loan->id,
            'amount'        => 400,
        ]);
    });

    it('accumulates amount_paid across multiple partial payments', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 5000]);
        $loan = LoanGiven::factory()->create([
            'user_id' => $user->id, 'account_id' => $account->id,
            'principal_amount' => 1000, 'balance' => 1000, 'amount_paid' => 0,
        ]);

        $this->actingAs($user)->post(route('loans-given.payment', $loan), [
            'payment_account_id' => $account->id,
            'payment_amount'     => 300,
            'payment_date'       => now()->toDateString(),
        ]);

        $this->actingAs($user)->post(route('loans-given.payment', $loan), [
            'payment_account_id' => $account->id,
            'payment_amount'     => 200,
            'payment_date'       => now()->toDateString(),
        ]);

        $loan->refresh();
        expect((float) $loan->amount_paid)->toBe(500.0)
            ->and((float) $loan->balance)->toBe(500.0);

        $this->assertDatabaseCount('loan_given_payments', 2);
    });

    it('rejects a payment on a loan that is not active', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa']);
        $loan = LoanGiven::factory()->paid()->create([
            'user_id' => $user->id, 'account_id' => $account->id,
        ]);

        $this->actingAs($user)
            ->post(route('loans-given.payment', $loan), [
                'payment_account_id' => $account->id,
                'payment_amount'     => 100,
                'payment_date'       => now()->toDateString(),
            ])
            ->assertSessionHas('error', 'Only active loans can receive repayments');
    });

    it('closes the loan and computes interest when close_loan is checked', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 5000]);
        $loan = LoanGiven::factory()->create([
            'user_id' => $user->id, 'account_id' => $account->id,
            'principal_amount' => 1000, 'balance' => 1000, 'amount_paid' => 0,
        ]);

        $this->actingAs($user)->post(route('loans-given.payment', $loan), [
            'payment_account_id' => $account->id,
            'payment_amount'     => 1100, // 100 profit
            'payment_date'       => now()->toDateString(),
            'close_loan'         => '1',
        ]);

        $loan->refresh();
        expect($loan->status)->toBe('paid')
            ->and((float) $loan->interest_amount)->toBe(100.0)
            ->and((float) $loan->interest_rate)->toBe(10.0)
            ->and((float) $loan->balance)->toBe(0.0)
            ->and($loan->repaid_date)->not->toBeNull();
    });

    it('splits interest into a separate Loan Interest income transaction on close', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 5000]);
        $loan = LoanGiven::factory()->create([
            'user_id' => $user->id, 'account_id' => $account->id,
            'principal_amount' => 1000, 'balance' => 1000, 'amount_paid' => 0,
        ]);

        $this->actingAs($user)->post(route('loans-given.payment', $loan), [
            'payment_account_id' => $account->id,
            'payment_amount'     => 1100,
            'payment_date'       => now()->toDateString(),
            'close_loan'         => '1',
        ]);

        // The original recovery transaction should be reduced to principal-only.
        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'amount'     => 1000,
            'type'       => 'income',
        ]);

        // A distinct Loan Interest transaction should hold the profit.
        $interestCategory = Category::where('user_id', $user->id)->where('name', 'Loan Interest')->first();
        expect($interestCategory)->not->toBeNull()
            ->and($interestCategory->type)->toBe('income');

        $this->assertDatabaseHas('transactions', [
            'account_id'  => $account->id,
            'category_id' => $interestCategory->id,
            'amount'      => 100,
            'type'        => 'income',
        ]);

        // Total cash recorded across both transactions must equal what actually came in.
        $total = Transaction::where('account_id', $account->id)
            ->where('type', 'income')
            ->sum('amount');
        expect((float) $total)->toBe(1100.0);
    });

    it('does not create a Loan Interest transaction when no interest was earned', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 5000]);
        $loan = LoanGiven::factory()->create([
            'user_id' => $user->id, 'account_id' => $account->id,
            'principal_amount' => 1000, 'balance' => 1000, 'amount_paid' => 0,
        ]);

        $this->actingAs($user)->post(route('loans-given.payment', $loan), [
            'payment_account_id' => $account->id,
            'payment_amount'     => 1000, // exact principal, no profit
            'payment_date'       => now()->toDateString(),
            'close_loan'         => '1',
        ]);

        expect(Category::where('user_id', $user->id)->where('name', 'Loan Interest')->exists())->toBeFalse();

        $loan->refresh();
        expect((float) $loan->interest_amount)->toBe(0.0);
    });

    it('deletes the recovery transaction entirely if the final payment was pure profit', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 5000]);
        $loan = LoanGiven::factory()->create([
            'user_id' => $user->id, 'account_id' => $account->id,
            'principal_amount' => 1000, 'balance' => 0, 'amount_paid' => 1000,
        ]);

        $incomeCategory = Category::factory()->create([
            'user_id' => $user->id, 'type' => 'income', 'is_active' => true, 'parent_id' => null,
        ]);

        // Principal already fully recovered via an earlier payment.
        $firstTxn = Transaction::factory()->create([
            'user_id' => $user->id, 'account_id' => $account->id,
            'category_id' => $incomeCategory->id,
            'amount' => 1000, 'type' => 'income', 'date' => now()->subDay(),
        ]);
        LoanGivenPayment::factory()->create([
            'user_id' => $user->id, 'loan_given_id' => $loan->id,
            'account_id' => $account->id, 'transaction_id' => $firstTxn->id,
            'amount' => 1000, 'payment_date' => now()->subDay(),
        ]);

        // Final "payment" is pure profit on top of an already-repaid principal.
        $this->actingAs($user)->post(route('loans-given.payment', $loan), [
            'payment_account_id' => $account->id,
            'payment_amount'     => 150,
            'payment_date'       => now()->toDateString(),
            'close_loan'         => '1',
        ]);

        $loan->refresh();
        expect((float) $loan->interest_amount)->toBe(150.0);

        // The transaction tied to this final, pure-interest payment should be gone —
        // not left behind as a zero/negative "Loan Recovery" row.
        $lastPayment = $loan->payments()->orderByDesc('id')->first();
        expect(Transaction::find($lastPayment->transaction_id))->toBeNull();

        $interestCategory = Category::where('user_id', $user->id)->where('name', 'Loan Interest')->first();
        $this->assertDatabaseHas('transactions', [
            'category_id' => $interestCategory->id,
            'amount'      => 150,
        ]);
    });
});

// ─── Standalone close ────────────────────────────────────────────────────────

describe('Closing a loan directly', function () {

    it('closes an active loan and computes interest from prior payments', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 5000]);
        $loan = LoanGiven::factory()->create([
            'user_id' => $user->id, 'account_id' => $account->id,
            'principal_amount' => 1000, 'balance' => 100, 'amount_paid' => 900,
        ]);

        $incomeCategory = Category::factory()->create([
            'user_id' => $user->id, 'type' => 'income', 'is_active' => true, 'parent_id' => null,
        ]);

        $txn = Transaction::factory()->create([
            'user_id' => $user->id, 'account_id' => $account->id,
            'category_id' => $incomeCategory->id,
            'amount' => 900, 'type' => 'income', 'date' => now(),
        ]);
        LoanGivenPayment::factory()->create([
            'user_id' => $user->id, 'loan_given_id' => $loan->id,
            'account_id' => $account->id, 'transaction_id' => $txn->id,
            'amount' => 900, 'payment_date' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('loans-given.close', $loan))
            ->assertRedirect();

        $loan->refresh();
        expect($loan->status)->toBe('paid')
            ->and((float) $loan->interest_amount)->toBe(0.0) // 900 paid < 1000 principal, no surplus
            ->and((float) $loan->balance)->toBe(0.0);
    });

    it('rejects closing a loan that is not active', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa']);
        $loan = LoanGiven::factory()->paid()->create([
            'user_id' => $user->id, 'account_id' => $account->id,
        ]);

        $this->actingAs($user)
            ->post(route('loans-given.close', $loan))
            ->assertSessionHas('error', 'Only active loans can be closed as repaid');
    });
});

// ─── Mark status ─────────────────────────────────────────────────────────────

describe('Marking loan status', function () {

    it('marks an active loan as defaulted', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa']);
        $loan = LoanGiven::factory()->create(['user_id' => $user->id, 'account_id' => $account->id]);

        $this->actingAs($user)
            ->put(route('loans-given.status', $loan), ['status' => 'defaulted'])
            ->assertRedirect();

        expect($loan->fresh()->status)->toBe('defaulted');
    });

    it('prevents changing the status of a fully paid loan', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa']);
        $loan = LoanGiven::factory()->paid()->create(['user_id' => $user->id, 'account_id' => $account->id]);

        $this->actingAs($user)
            ->put(route('loans-given.status', $loan), ['status' => 'defaulted'])
            ->assertSessionHas('error', 'Cannot change status of a fully paid loan');

        expect($loan->fresh()->status)->toBe('paid');
    });
});

// ─── Destroy ─────────────────────────────────────────────────────────────────

describe('Deleting a loan given', function () {

    it('deletes an active loan with no repayments and its disbursement transaction', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 5000]);

        $this->actingAs($user)->post(route('loans-given.store'), [
            'borrower_name'    => 'John Doe',
            'account_id'       => $account->id,
            'principal_amount' => 500,
            'disbursed_date'   => now()->toDateString(),
        ]);

        $loan = LoanGiven::where('borrower_name', 'John Doe')->first();

        $this->actingAs($user)
            ->delete(route('loans-given.destroy', $loan))
            ->assertRedirect(route('loans-given.index'));

        $this->assertDatabaseMissing('loans_given', ['id' => $loan->id]);
        $this->assertDatabaseMissing('transactions', ['description' => 'Loan disbursed to John Doe']);
    });

    it('prevents deleting a loan that has received any repayment', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa']);
        $loan = LoanGiven::factory()->partiallyPaid()->create([
            'user_id' => $user->id, 'account_id' => $account->id,
        ]);

        $this->actingAs($user)
            ->delete(route('loans-given.destroy', $loan))
            ->assertSessionHas('error', 'Cannot delete loans that have received partial or full repayment');

        $this->assertDatabaseHas('loans_given', ['id' => $loan->id]);
    });

    it('prevents deleting a non-active loan', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa']);
        $loan = LoanGiven::factory()->paid()->create([
            'user_id' => $user->id, 'account_id' => $account->id, 'amount_paid' => 0,
        ]);

        $this->actingAs($user)
            ->delete(route('loans-given.destroy', $loan))
            ->assertSessionHas('error', 'Cannot delete non-active loans');
    });
});

// ─── Budget dashboard integration ────────────────────────────────────────────

describe('Loan given transactions and the Budget dashboard', function () {

    it('excludes an unpaid loan disbursement from Budget expenses', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 5000]);

        $this->actingAs($user)->post(route('loans-given.store'), [
            'borrower_name'    => 'John Doe',
            'account_id'       => $account->id,
            'principal_amount' => 1000,
            'disbursed_date'   => now()->toDateString(),
        ]);

        $response = $this->actingAs($user)->get(route('budgets.index'));

        $response->assertOk();
        $expenseCategories = $response->viewData('expenseCategories');
        expect($expenseCategories->pluck('name'))->not->toContain('Friend Loan Given');
    });

    it('excludes principal recovery but includes Loan Interest as real income once closed', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'mpesa', 'current_balance' => 5000]);
        $loan = LoanGiven::factory()->create([
            'user_id' => $user->id, 'account_id' => $account->id,
            'principal_amount' => 1000, 'balance' => 1000, 'amount_paid' => 0,
        ]);

        $this->actingAs($user)->post(route('loans-given.payment', $loan), [
            'payment_account_id' => $account->id,
            'payment_amount'     => 1150,
            'payment_date'       => now()->toDateString(),
            'close_loan'         => '1',
        ]);

        $response = $this->actingAs($user)->get(route('budgets.index'));
        $response->assertOk();

        $incomeCategories = $response->viewData('incomeCategories');
        expect($incomeCategories->pluck('name'))
            ->not->toContain('Loan Recovery')
            ->toContain('Loan Interest');

        $interestRow = $incomeCategories->firstWhere('name', 'Loan Interest');
        expect((float) $interestRow->yearly_total)->toBe(150.0);
    });
});
