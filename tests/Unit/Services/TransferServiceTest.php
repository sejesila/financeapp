<?php

namespace Tests\Unit\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TransferServiceTest extends TestCase
{
    use RefreshDatabase;

    private TransferService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TransferService::class);
        $this->user    = User::factory()->create();
        $this->actingAs($this->user);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeAccounts(string $fromType, string $toType, float $balance = 10_000): array
    {
        $from = Account::factory()->create([
            'user_id'         => $this->user->id,
            'type'            => $fromType,
            'current_balance' => $balance,
            'initial_balance' => $balance,
        ]);

        $to = Account::factory()->create([
            'user_id'         => $this->user->id,
            'type'            => $toType,
            'current_balance' => 0,
            'initial_balance' => 0,
        ]);

        return [$from, $to];
    }

    private function execute(Account $from, Account $to, float $amount, ?string $description = null, ?float $fee = null)
    {
        return $this->service->execute($from, $to, $amount, now()->toDateString(), $description, $fee);
    }

    // ── Transfer rule validation ──────────────────────────────────────────────

    public function test_blocks_direct_transfer_from_cash_to_savings()
    {
        [$from, $to] = $this->makeAccounts('cash', 'savings');

        $this->expectException(ValidationException::class);

        $this->execute($from, $to, 1000);
    }

    public function test_cash_to_savings_error_references_mobile_money()
    {
        [$from, $to] = $this->makeAccounts('cash', 'savings');

        try {
            $this->execute($from, $to, 1000);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('to_account_id', $e->errors());
            $this->assertStringContainsString('M-Pesa', $e->errors()['to_account_id'][0]);
        }
    }

    public function test_allows_cash_to_mpesa()
    {
        [$from, $to] = $this->makeAccounts('cash', 'mpesa');

        $fee = $this->execute($from, $to, 500);

        $this->assertNotNull($fee);
    }

    public function test_allows_savings_to_cash()
    {
        [$from, $to] = $this->makeAccounts('savings', 'cash');

        $fee = $this->execute($from, $to, 500);

        $this->assertNotNull($fee);
    }

    public function test_allows_savings_to_mpesa()
    {
        [$from, $to] = $this->makeAccounts('savings', 'mpesa');

        $fee = $this->execute($from, $to, 500);

        $this->assertNotNull($fee);
    }

    public function test_allows_savings_to_airtel_money()
    {
        [$from, $to] = $this->makeAccounts('savings', 'airtel_money');

        $fee = $this->execute($from, $to, 500);

        $this->assertNotNull($fee);
    }

    public function test_allows_savings_to_bank()
    {
        [$from, $to] = $this->makeAccounts('savings', 'bank');

        $fee = $this->execute($from, $to, 500);

        $this->assertNotNull($fee);
    }

    public function test_blocks_savings_to_wallet()
    {
        [$from, $to] = $this->makeAccounts('savings', 'wallet');

        $this->expectException(ValidationException::class);

        $this->execute($from, $to, 500);
    }

    public function test_blocks_savings_to_savings()
    {
        [$from, $to] = $this->makeAccounts('savings', 'savings');

        $this->expectException(ValidationException::class);

        $this->execute($from, $to, 500);
    }

    public function test_savings_to_disallowed_type_error_lists_allowed_types()
    {
        [$from, $to] = $this->makeAccounts('savings', 'wallet');

        try {
            $this->execute($from, $to, 500);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('to_account_id', $e->errors());
            $this->assertStringContainsString('Cash', $e->errors()['to_account_id'][0]);
        }
    }

    // ── M-Pesa minimum withdrawal ─────────────────────────────────────────────

    public function test_blocks_mpesa_to_cash_below_kes_50()
    {
        [$from, $to] = $this->makeAccounts('mpesa', 'cash');

        $this->expectException(ValidationException::class);

        $this->execute($from, $to, 49);
    }

    public function test_allows_mpesa_to_cash_at_exactly_kes_50()
    {
        [$from, $to] = $this->makeAccounts('mpesa', 'cash');

        $fee = $this->execute($from, $to, 50);

        $this->assertNotNull($fee);
    }

    public function test_allows_mpesa_to_cash_above_kes_50()
    {
        [$from, $to] = $this->makeAccounts('mpesa', 'cash');

        $fee = $this->execute($from, $to, 500);

        $this->assertNotNull($fee);
    }

    public function test_mpesa_minimum_does_not_apply_to_non_cash_destinations()
    {
        // KES 10 from MPesa to Bank — would fail if the KES-50 rule wrongly applied here
        [$from, $to] = $this->makeAccounts('mpesa', 'bank');

        $fee = $this->execute($from, $to, 10);

        $this->assertNotNull($fee);
    }

    // ── Balance check ─────────────────────────────────────────────────────────

    public function test_blocks_transfer_when_balance_is_insufficient()
    {
        [$from, $to] = $this->makeAccounts('cash', 'mpesa', 100);

        $this->expectException(ValidationException::class);

        $this->execute($from, $to, 101);
    }

    public function test_allows_transfer_when_balance_exactly_covers_amount()
    {
        // Cash → MPesa has no fee so 500 balance covers 500 exactly
        [$from, $to] = $this->makeAccounts('cash', 'mpesa', 500);

        $fee = $this->execute($from, $to, 500);

        $this->assertNotNull($fee);
    }

    public function test_insufficient_balance_error_contains_account_name_and_amounts()
    {
        [$from, $to] = $this->makeAccounts('cash', 'mpesa', 100);

        try {
            $this->execute($from, $to, 500);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
            $msg = $e->errors()['amount'][0];
            $this->assertStringContainsString('Insufficient balance', $msg);
            $this->assertStringContainsString($from->name, $msg);
        }
    }

    public function test_balance_check_includes_fee_in_required_total()
    {
        // MPesa → Cash carries a fee; balance of 50 covers the transfer but not the fee on top
        [$from, $to] = $this->makeAccounts('mpesa', 'cash', 50);

        $this->expectException(ValidationException::class);

        $this->execute($from, $to, 50);
    }

    // ── Transfer record creation ──────────────────────────────────────────────

    public function test_creates_one_transfer_record_on_success()
    {
        [$from, $to] = $this->makeAccounts('cash', 'mpesa');

        $this->execute($from, $to, 1000, 'Test transfer');

        $this->assertCount(1, Transfer::all());
    }

    public function test_transfer_record_has_correct_accounts_amount_and_description()
    {
        [$from, $to] = $this->makeAccounts('cash', 'mpesa');

        $this->execute($from, $to, 1000, 'Test transfer');

        $transfer = Transfer::first();
        $this->assertEquals($from->id, $transfer->from_account_id);
        $this->assertEquals($to->id, $transfer->to_account_id);
        $this->assertEquals(1000.0, (float) $transfer->amount);
        $this->assertEquals('Test transfer', $transfer->description);
    }

    public function test_transfer_record_stores_date_correctly()
    {
        [$from, $to] = $this->makeAccounts('cash', 'mpesa');

        $this->service->execute($from, $to, 500, '2025-03-15');

        $this->assertEquals('2025-03-15', Transfer::first()->date->toDateString());
    }

    public function test_transfer_record_stores_null_description_when_none_provided()
    {
        [$from, $to] = $this->makeAccounts('cash', 'mpesa');

        $this->execute($from, $to, 500);

        $this->assertNull(Transfer::first()->description);
    }

    // ── Balance updates ───────────────────────────────────────────────────────

    public function test_debits_source_account_after_transfer()
    {
        [$from, $to] = $this->makeAccounts('cash', 'mpesa', 5000);

        $this->execute($from, $to, 1000);

        $this->assertEquals(4000.0, (float) $from->fresh()->current_balance);
    }

    public function test_credits_destination_account_after_transfer()
    {
        [$from, $to] = $this->makeAccounts('cash', 'mpesa', 5000);

        $this->execute($from, $to, 1000);

        $this->assertEquals(1000.0, (float) $to->fresh()->current_balance);
    }

    // ── Fee recording ─────────────────────────────────────────────────────────

    public function test_records_fee_transaction_when_fee_applies()
    {
        // MPesa → Cash carries a withdrawal fee
        [$from, $to] = $this->makeAccounts('mpesa', 'cash', 10_000);

        $this->execute($from, $to, 1000);

        $feeTransaction = Transaction::where('is_transaction_fee', true)->first();
        $this->assertNotNull($feeTransaction);
        $this->assertEquals($from->id, $feeTransaction->account_id);
    }

    public function test_no_fee_transaction_when_no_fee_applies()
    {
        // Cash → MPesa has no fee
        [$from, $to] = $this->makeAccounts('cash', 'mpesa', 10_000);

        $this->execute($from, $to, 1000);

        $this->assertEquals(0, Transaction::where('is_transaction_fee', true)->count());
    }

    public function test_second_transfer_reuses_existing_transaction_fees_category()
    {
        [$from, $to] = $this->makeAccounts('mpesa', 'cash', 10_000);

        $this->execute($from, $to, 1000);
        $this->execute($from, $to, 1000);

        $this->assertEquals(1, Category::where('name', 'Transaction Fees')->count());
        $this->assertEquals(2, Transaction::where('is_transaction_fee', true)->count());
    }

    public function test_fee_description_includes_user_description_when_provided()
    {
        [$from, $to] = $this->makeAccounts('mpesa', 'cash', 10_000);

        $this->execute($from, $to, 1000, 'School fees');

        $feeTransaction = Transaction::where('is_transaction_fee', true)->first();
        $this->assertStringContainsString('School fees', $feeTransaction->description);
    }

    public function test_both_transfer_amount_and_fee_debited_from_source()
    {
        [$from, $to] = $this->makeAccounts('mpesa', 'cash', 10_000);

        $fee = $this->execute($from, $to, 1000);

        $expectedBalance = 10_000 - 1000 - $fee->amount;
        $this->assertEquals((float) $expectedBalance, (float) $from->fresh()->current_balance);
    }

    // ── Manual fee override ───────────────────────────────────────────────────

    public function test_manual_fee_overrides_calculated_fee()
    {
        [$from, $to] = $this->makeAccounts('mpesa', 'cash', 10_000);

        $this->execute($from, $to, 1000, null, 99.0);

        $feeTransaction = Transaction::where('is_transaction_fee', true)->first();
        $this->assertNotNull($feeTransaction);
        $this->assertEquals(99.0, (float) $feeTransaction->amount);
    }

    public function test_zero_manual_fee_suppresses_fee_transaction()
    {
        // MPesa → Cash normally carries a fee, but user overrides to 0
        [$from, $to] = $this->makeAccounts('mpesa', 'cash', 10_000);

        $this->execute($from, $to, 1000, null, 0.0);

        $this->assertEquals(0, Transaction::where('is_transaction_fee', true)->count());
    }

    // ── Atomicity ─────────────────────────────────────────────────────────────

    public function test_no_records_persisted_when_validation_fails()
    {
        // Balance too low — enforceBalanceCheck throws after enforceTransferRules passes
        [$from, $to] = $this->makeAccounts('cash', 'mpesa', 10);

        try {
            $this->execute($from, $to, 1000);
        } catch (ValidationException) {
            // expected
        }

        $this->assertEquals(0, Transfer::count());
        $this->assertEquals(0, Transaction::where('is_transaction_fee', true)->count());
        $this->assertEquals(10.0, (float) $from->fresh()->current_balance);
        $this->assertEquals(0.0, (float) $to->fresh()->current_balance);
    }
}
