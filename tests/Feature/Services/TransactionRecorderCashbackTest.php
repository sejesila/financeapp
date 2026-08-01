<?php
// tests/Feature/Services/TransactionRecorderCashbackTest.php

namespace Tests\Feature\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CategoryResolver;
use App\Services\TransactionRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionRecorderCashbackTest extends TestCase
{
    use RefreshDatabase;

    private TransactionRecorder $recorder;
    private User $user;
    private Account $airtelAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recorder = new TransactionRecorder(new CategoryResolver());

        $this->user = User::factory()->create();

        $this->airtelAccount = Account::withoutGlobalScopes()->create([
            'user_id'   => $this->user->id,
            'type'      => 'airtel_money',
            'name'      => 'Airtel Money',
            'is_active' => true,
        ]);
    }

    private function makeFeeTransaction(float $amount, \DateTimeInterface|string $date, string $ref = 'REF001'): Transaction
    {
        $category = Category::withoutGlobalScopes()->firstOrCreate(
            ['user_id' => $this->user->id, 'name' => 'Transaction Fees'],
            ['type' => 'expense']
        );

        return Transaction::withoutGlobalScopes()->create([
            'user_id'            => $this->user->id,
            'account_id'         => $this->airtelAccount->id,
            'category_id'        => $category->id,
            'amount'             => $amount,
            'date'               => $date,
            'description'        => "Transaction fee for {$ref}",
            'payment_method'     => 'Airtel Money',
            'is_transaction_fee' => true,
        ]);
    }

    private function cashbackPayload(float $amount, \Carbon\Carbon $date, string $ref = 'Q3PH3911CC5'): array
    {
        return [
            'bank'        => 'airtel',
            'type'        => 'income',
            'subtype'     => 'airtelcashback',
            'reference'   => $ref,
            'amount'      => $amount,
            'phone'       => '0731xxx277',
            'date'        => $date,
            'balance'     => 0.65,
            'wallet_balance' => 14452.00,
            'fee'         => 0,
            'description' => 'Airtel Cashback/Bonus moved to wallet',
        ];
    }

    // ── 1. Happy path ────────────────────────────────────────────────────

    public function test_cashback_reduces_preceding_fee()
    {
        $feeDate = now()->subDays(2);
        $fee = $this->makeFeeTransaction(57.00, $feeDate, 'UDT882GV6I');

        $cashbackDate = now();
        $payload = $this->cashbackPayload(25.00, $cashbackDate);

        $response = $this->recorder->applyCashback($this->user, $payload);
        $data = $response->getData(true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('cashback_applied', $data['status']);
        $this->assertEquals(57.00, $data['original_fee']);
        $this->assertEquals(25.00, $data['cashback']);
        $this->assertEquals(32.00, $data['new_fee']);

        $fee->refresh();
        $this->assertEquals(32.00, $fee->amount);
        $this->assertStringContainsString('cashback applied', $fee->description);
        $this->assertStringContainsString($payload['reference'], $fee->description);
    }

    // ── 2. Fallback to income when no fee has enough headroom ──────────────

    public function test_falls_back_to_income_when_no_fee_has_enough_headroom()
    {
        $this->makeFeeTransaction(10.00, now()->subDays(1), 'UDT882SMALL');

        $payload = $this->cashbackPayload(25.00, now());

        $response = $this->recorder->applyCashback($this->user, $payload);
        $data = $response->getData(true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('created', $data['status']);
        $this->assertEquals('income', $data['type']);

        $this->assertDatabaseHas('transactions', [
            'amount'             => 10.00,
            'is_transaction_fee' => true,
        ]);

        $this->assertDatabaseHas('transactions', [
            'amount'             => 25.00,
            'is_transaction_fee' => false,
        ]);
    }

    public function test_falls_back_to_income_when_no_fee_exists_at_all()
    {
        $payload = $this->cashbackPayload(25.00, now());

        $response = $this->recorder->applyCashback($this->user, $payload);
        $data = $response->getData(true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('created', $data['status']);
        $this->assertEquals('income', $data['type']);
    }

    // ── 3. Already-reduced fee becomes ineligible ───────────────────────────

    public function test_fee_already_reduced_by_prior_cashback_is_ineligible_for_larger_cashback()
    {
        $fee = $this->makeFeeTransaction(57.00, now()->subDays(5), 'UDT882GV6I');
        $fee->update(['amount' => 5.00]);

        $payload = $this->cashbackPayload(25.00, now());

        $response = $this->recorder->applyCashback($this->user, $payload);
        $data = $response->getData(true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('created', $data['status']);
        $this->assertEquals('income', $data['type']);

        $fee->refresh();
        $this->assertEquals(5.00, $fee->amount);
    }

    public function test_fee_already_reduced_can_still_absorb_a_smaller_cashback()
    {
        $fee = $this->makeFeeTransaction(57.00, now()->subDays(5), 'UDT882GV6I');
        $fee->update(['amount' => 20.00]);

        $payload = $this->cashbackPayload(15.00, now());

        $response = $this->recorder->applyCashback($this->user, $payload);
        $data = $response->getData(true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('cashback_applied', $data['status']);
        $this->assertEquals(5.00, $data['new_fee']);
    }

    // ── 4 & 5. 30-day boundary ───────────────────────────────────────────

    public function test_fee_exactly_31_days_old_is_excluded()
    {
        $cashbackDate = now();
        $this->makeFeeTransaction(57.00, $cashbackDate->copy()->subDays(31), 'UDT882OLD');

        $payload = $this->cashbackPayload(25.00, $cashbackDate);

        $response = $this->recorder->applyCashback($this->user, $payload);
        $data = $response->getData(true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('created', $data['status']);
        $this->assertEquals('income', $data['type']);
    }

    public function test_fee_exactly_30_days_old_is_included()
    {
        $cashbackDate = now();
        $fee = $this->makeFeeTransaction(57.00, $cashbackDate->copy()->subDays(30), 'UDT882EDGE');

        $payload = $this->cashbackPayload(25.00, $cashbackDate);

        $response = $this->recorder->applyCashback($this->user, $payload);
        $data = $response->getData(true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('cashback_applied', $data['status']);

        $fee->refresh();
        $this->assertEquals(32.00, $fee->amount);
    }

    // ── 6. Nearest preceding fee is chosen, not the largest ────────────────

    public function test_nearest_preceding_fee_chosen_over_larger_older_fee()
    {
        $cashbackDate = now();

        $olderFee = $this->makeFeeTransaction(200.00, $cashbackDate->copy()->subDays(10), 'UDT882OLDER');
        $newerFee = $this->makeFeeTransaction(57.00, $cashbackDate->copy()->subDays(1), 'UDT882NEWER');

        $payload = $this->cashbackPayload(25.00, $cashbackDate);

        $response = $this->recorder->applyCashback($this->user, $payload);
        $data = $response->getData(true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($newerFee->id, $data['fee_transaction_id']);

        $newerFee->refresh();
        $olderFee->refresh();

        $this->assertEquals(32.00, $newerFee->amount);
        $this->assertEquals(200.00, $olderFee->amount);
    }
    // ── 7. No matching Airtel Money account ───────────────────────────────

    public function test_returns_404_when_no_airtel_account_exists()
    {
        $userWithoutAirtel = User::factory()->create();

        $payload = $this->cashbackPayload(25.00, now());

        $response = $this->recorder->applyCashback($userWithoutAirtel, $payload);
        $data = $response->getData(true);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('No matching account found', $data['error']);
    }
    // ── 8. Balance correctly reflects the reduced fee ───────────────────────

    public function test_account_balance_increases_when_fee_is_reduced()
    {
        // Establish a baseline: one income transaction + one fee, then capture balance
        $incomeCategory = Category::withoutGlobalScopes()->firstOrCreate(
            ['user_id' => $this->user->id, 'name' => 'Side Income', 'type' => 'income'],
            ['is_active' => true]
        );

        Transaction::withoutGlobalScopes()->create([
            'user_id'     => $this->user->id,
            'account_id'  => $this->airtelAccount->id,
            'category_id' => $incomeCategory->id,
            'amount'      => 1000.00,
            'date'        => now()->subDays(3),
            'description' => 'Seed deposit',
        ]);

        $fee = $this->makeFeeTransaction(57.00, now()->subDays(2), 'UDT882GV6I');

        $this->airtelAccount->updateBalance();
        $this->airtelAccount->refresh();
        $balanceBeforeCashback = (float) $this->airtelAccount->current_balance;

        // Sanity check: 1000 income - 57 fee = 943
        $this->assertEquals(943.00, $balanceBeforeCashback);

        // Apply a 25 cashback — should reduce fee to 32, freeing up 25 in balance
        $payload = $this->cashbackPayload(25.00, now());
        $this->recorder->applyCashback($this->user, $payload);

        $this->airtelAccount->refresh();
        $balanceAfterCashback = (float) $this->airtelAccount->current_balance;

        $this->assertEquals($balanceBeforeCashback + 25.00, $balanceAfterCashback);
        $this->assertEquals(968.00, $balanceAfterCashback);

        $fee->refresh();
        $this->assertEquals(32.00, $fee->amount);
    }
}
