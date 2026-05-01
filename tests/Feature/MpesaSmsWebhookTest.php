<?php
// tests/Feature/MpesaSmsWebhookTest.php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function webhookPost(array $body, string $secret = 'test-secret'): \Illuminate\Testing\TestResponse
{
    return test()->postJson('/webhook/mpesa-sms', $body, [
        'X-Webhook-Secret' => $secret,
    ]);
}

function makeWebhookUser(): User
{
    return User::factory()->create();
}

function makeMpesaAccount(User $user, float $balance = 50000): Account
{
    return Account::factory()->create([
        'user_id'         => $user->id,
        'type'            => 'mpesa',
        'name'            => 'Mpesa',
        'current_balance' => $balance,
        'initial_balance' => $balance,
        'is_active'       => true,
    ]);
}

function makeBankAccount(User $user, float $balance = 200000): Account
{
    return Account::factory()->create([
        'user_id'         => $user->id,
        'type'            => 'bank',
        'name'            => 'I&M Bank',
        'current_balance' => $balance,
        'initial_balance' => $balance,
        'is_active'       => true,
    ]);
}

function makeCashAccount(User $user, float $balance = 5000): Account
{
    return Account::factory()->create([
        'user_id'         => $user->id,
        'type'            => 'cash',
        'name'            => 'Cash',
        'current_balance' => $balance,
        'initial_balance' => $balance,
        'is_active'       => true,
    ]);
}

function makeAirtelAccount(User $user, float $balance = 0): Account
{
    return Account::factory()->create([
        'user_id'         => $user->id,
        'type'            => 'airtel_money',
        'name'            => 'Airtel Money',
        'current_balance' => $balance,
        'initial_balance' => $balance,
        'is_active'       => true,
    ]);
}

function makeSanlamAccount(User $user, float $balance = 0): Account
{
    return Account::factory()->create([
        'user_id'         => $user->id,
        'type'            => 'savings',
        'name'            => 'Sanlam MMF',
        'current_balance' => $balance,
        'initial_balance' => $balance,
        'is_active'       => true,
    ]);
}

beforeEach(function () {
    config(['services.mpesa_webhook.secret' => 'test-secret']);
});

// ── Authentication ────────────────────────────────────────────────────────────

describe('authentication', function () {

    it('rejects requests with wrong secret', function () {
        $user = makeWebhookUser();

        webhookPost(['user_id' => $user->id, 'sms' => 'test'], 'wrong-secret')
            ->assertStatus(401)
            ->assertJson(['error' => 'Unauthorized']);
    });

    it('rejects requests with missing secret', function () {
        $user = makeWebhookUser();

        test()->postJson('/webhook/mpesa-sms', ['user_id' => $user->id, 'sms' => 'test'])
            ->assertStatus(401);
    });

    it('accepts requests with correct secret in header', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'FH6KL9O1RE Confirmed. KES500.00 sent to JOHN DOE 0712345678 on 30/4/26 at 8:23 AM. New M-PESA balance is KES13,000.00. Transaction cost, KES29.00.',
        ])->assertStatus(201);
    });

    it('accepts secret passed as body parameter', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user);

        test()->postJson('/webhook/mpesa-sms', [
            'user_id' => $user->id,
            'secret'  => 'test-secret',
            'sms'     => 'FH6KL9O1RE Confirmed. KES500.00 sent to JOHN DOE 0712345678 on 30/4/26 at 8:23 AM. New M-PESA balance is KES13,000.00. Transaction cost, KES29.00.',
        ])->assertStatus(201);
    });
});

// ── Validation ────────────────────────────────────────────────────────────────

describe('validation', function () {

    it('returns 404 when user not found', function () {
        webhookPost(['user_id' => 99999, 'sms' => 'test'])
            ->assertStatus(404)
            ->assertJson(['error' => 'User not found']);
    });

    it('returns 422 when sms body is missing', function () {
        $user = makeWebhookUser();

        webhookPost(['user_id' => $user->id])
            ->assertStatus(422)
            ->assertJson(['error' => 'No SMS body provided']);
    });

    it('returns ignored status for unrecognised SMS', function () {
        $user = makeWebhookUser();

        webhookPost(['user_id' => $user->id, 'sms' => 'Your OTP is 123456'])
            ->assertStatus(200)
            ->assertJson(['status' => 'ignored']);
    });
});

// ── Mpesa: Send Money ─────────────────────────────────────────────────────────

describe('mpesa send money', function () {

    it('creates a transaction for mpesa send money', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'FH6KL9O1RE Confirmed. KES1,500.00 sent to JOHN DOE 0712345678 on 30/4/26 at 8:23 AM. New M-PESA balance is KES13,500.00. Transaction cost, KES27.00.',
        ])->assertStatus(201)
            ->assertJson([
                'status'  => 'created',
                'bank'    => 'mpesa',
                'subtype' => 'send_money',
                'amount'  => 1500.0,
            ]);

        expect(Transaction::withoutGlobalScopes()->where('user_id', $user->id)->count())->toBe(2); // main + fee
    });

    it('creates a fee transaction for mpesa send money', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'FH6KL9O1RE Confirmed. KES1,500.00 sent to JOHN DOE 0712345678 on 30/4/26 at 8:23 AM. New M-PESA balance is KES13,500.00. Transaction cost, KES27.00.',
        ]);

        expect(
            Transaction::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('is_transaction_fee', true)
                ->where('amount', 27.0)
                ->exists()
        )->toBeTrue();
    });

    it('assigns the correct category to send money', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'FH6KL9O1RE Confirmed. KES500.00 sent to JOHN DOE 0712345678 on 30/4/26 at 8:23 AM. New M-PESA balance is KES13,000.00. Transaction cost, KES29.00.',
        ]);

        $tx = Transaction::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('is_transaction_fee', false)
            ->first();

        expect($tx->category->name)->toBe('Other Expenses');
    });

    it('decreases mpesa balance by amount plus fee', function () {
        $user  = makeWebhookUser();
        $mpesa = makeMpesaAccount($user, 20000);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'FH6KL9O1RE Confirmed. KES500.00 sent to JOHN DOE 0712345678 on 30/4/26 at 8:23 AM. New M-PESA balance is KES19,471.00. Transaction cost, KES29.00.',
        ]);

        // 20000 - 500 (main tx) - 29 (fee tx) = 19471
        expect((float) $mpesa->fresh()->current_balance)->toBe(19471.0);
    });
});

// ── Mpesa: Receive Money ──────────────────────────────────────────────────────

describe('mpesa receive money', function () {

    it('creates an income transaction for mpesa received money', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UDG880ZSXQ Confirmed. You have received KES500.00 from JANE DOE 0722123456 on 16/4/26 at 2:00 PM. New M-PESA balance is KES6,000.00.',
        ])->assertStatus(201)
            ->assertJson(['subtype' => 'receive_money', 'type' => 'income']);

        expect(
            Transaction::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('is_transaction_fee', true)
                ->exists()
        )->toBeFalse();
    });

    it('categorises received money as Side Income', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user);

        Category::factory()->create(['user_id' => $user->id, 'name' => 'Side Income', 'type' => 'income']);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UDG880ZSXQ Confirmed. You have received KES500.00 from JANE DOE 0722123456 on 16/4/26 at 2:00 PM. New M-PESA balance is KES6,000.00.',
        ]);

        $tx = Transaction::withoutGlobalScopes()->where('user_id', $user->id)->first();
        expect($tx->category->name)->toBe('Side Income');
    });

    it('increases mpesa balance on received money', function () {
        $user  = makeWebhookUser();
        $mpesa = makeMpesaAccount($user, 5000);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UDG880ZSXQ Confirmed. You have received KES500.00 from JANE DOE 0722123456 on 16/4/26 at 2:00 PM. New M-PESA balance is KES5,500.00.',
        ]);

        expect((float) $mpesa->fresh()->current_balance)->toBe(5500.0);
    });
});

// ── Mpesa: Airtime ────────────────────────────────────────────────────────────

describe('mpesa airtime', function () {

    it('creates a transaction for airtime purchase', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UDU882MWE1 confirmed.You bought KES1.00 of airtime on 30/4/26 at 10:17 PM.New M-PESA balance is KES6,716.30. Transaction cost, KES0.00.',
        ])->assertStatus(201)
            ->assertJson(['subtype' => 'airtime', 'amount' => 1.0]);
    });

    it('categorises airtime as Airtime & Data', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UDU882MWE1 confirmed.You bought KES50.00 of airtime on 30/4/26 at 10:17 PM.New M-PESA balance is KES6,716.30. Transaction cost, KES0.00.',
        ]);

        $tx = Transaction::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('is_transaction_fee', false)
            ->first();

        expect($tx->category->name)->toBe('Airtime & Data');
    });

    it('does not create a fee transaction when airtime fee is zero', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UDU882MWE1 confirmed.You bought KES1.00 of airtime on 30/4/26 at 10:17 PM.New M-PESA balance is KES6,716.30. Transaction cost, KES0.00.',
        ]);

        expect(
            Transaction::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('is_transaction_fee', true)
                ->exists()
        )->toBeFalse();
    });

    it('decreases mpesa balance by airtime amount', function () {
        $user  = makeWebhookUser();
        $mpesa = makeMpesaAccount($user, 10000);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UDU882MWE1 confirmed.You bought KES50.00 of airtime on 30/4/26 at 10:17 PM.New M-PESA balance is KES9,950.00. Transaction cost, KES0.00.',
        ]);

        expect((float) $mpesa->fresh()->current_balance)->toBe(9950.0);
    });
});

// ── Mpesa: Till / Lipa Na M-PESA ──────────────────────────────────────────────

describe('mpesa till payment', function () {

    it('creates a transaction for till payment', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UDT882GV6I Confirmed. KES450.00 paid to VUNO VENTURES. on 29/4/26 at 3:14 PM. New M-PESA balance is KES19,505.30. Transaction cost, KES0.00.',
        ])->assertStatus(201)
            ->assertJson([
                'status'  => 'created',
                'bank'    => 'mpesa',
                'subtype' => 'till',
                'amount'  => 450.0,
            ]);
    });

    it('categorises till payment as Groceries when matched', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UDT882GV6I Confirmed. KES450.00 paid to VUNO VENTURES. on 29/4/26 at 3:14 PM. New M-PESA balance is KES19,505.30. Transaction cost, KES0.00.',
        ]);

        $tx = Transaction::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('is_transaction_fee', false)
            ->first();

        expect($tx->category->name)->toBe('Groceries');
    });

    it('categorises unknown till as Other Expenses', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UDT882GV6I Confirmed. KES450.00 paid to RANDOM MERCHANT. on 29/4/26 at 3:14 PM. New M-PESA balance is KES19,505.30. Transaction cost, KES0.00.',
        ]);

        $tx = Transaction::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('is_transaction_fee', false)
            ->first();

        expect($tx->category->name)->toBe('Other Expenses');
    });

    it('decreases mpesa balance on till payment', function () {
        $user  = makeWebhookUser();
        $mpesa = makeMpesaAccount($user, 20000);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UDT882GV6I Confirmed. KES450.00 paid to VUNO VENTURES. on 29/4/26 at 3:14 PM. New M-PESA balance is KES19,550.00. Transaction cost, KES0.00.',
        ]);

        expect((float) $mpesa->fresh()->current_balance)->toBe(19550.0);
    });
});

// ── Mpesa: Paybill ────────────────────────────────────────────────────────────

describe('mpesa paybill', function () {

    it('creates an expense transaction for regular paybill', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UDU882IZYJ Confirmed. KES30.00 sent to WINAS SACCO for account 76586 on 30/4/26 at 6:19 AM New M-PESA balance is KES19,475.30. Transaction cost, KES0.00.',
        ])->assertStatus(201)
            ->assertJson([
                'status'  => 'created',
                'bank'    => 'mpesa',
                'type'    => 'expense',
                'subtype' => 'paybill',
                'amount'  => 30.0,
            ]);
    });

    it('maps KPLC paybill to Electricity', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'PAY123ABC Confirmed. KES2,000.00 sent to KPLC PREPAID for account 14229080958 on 5/5/26 at 9:00 AM. New M-PESA balance is KES8,000.00. Transaction cost, KES0.00.',
        ]);

        $tx = Transaction::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('is_transaction_fee', false)
            ->with('category')
            ->first();

        expect($tx->category->name)->toBe('Electricity');
    });

    it('maps Safaricom paybill to Internet and Communication', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'PAY456DEF Confirmed. KES1,000.00 sent to SAFARICOM HOME for account 987654 on 5/5/26 at 9:00 AM. New M-PESA balance is KES8,000.00. Transaction cost, KES10.00.',
        ]);

        $tx = Transaction::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('is_transaction_fee', false)
            ->with('category')
            ->first();

        expect($tx->category->name)->toBe('Internet and Communication');
    });

    it('maps unknown paybill to Other Expenses', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'PAY789GHI Confirmed. KES500.00 sent to RANDOM COMPANY for account 111222 on 5/5/26 at 9:00 AM. New M-PESA balance is KES8,000.00. Transaction cost, KES5.00.',
        ]);

        $tx = Transaction::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('is_transaction_fee', false)
            ->with('category')
            ->first();

        expect($tx->category->name)->toBe('Other Expenses');
    });

    it('decreases mpesa balance by amount plus fee on paybill', function () {
        $user  = makeWebhookUser();
        $mpesa = makeMpesaAccount($user, 10000);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'PAY456DEF Confirmed. KES1,000.00 sent to SAFARICOM HOME for account 987654 on 5/5/26 at 9:00 AM. New M-PESA balance is KES8,990.00. Transaction cost, KES10.00.',
        ]);

        // 10000 - 1000 (main) - 10 (fee) = 8990
        expect((float) $mpesa->fresh()->current_balance)->toBe(8990.0);
    });
});

// ── Paybill Transfer: Airtel Money ────────────────────────────────────────────

describe('paybill transfer — airtel money', function () {

    it('creates a transfer from mpesa to airtel money account', function () {
        $user   = makeWebhookUser();
        $mpesa  = makeMpesaAccount($user, 50000);
        $airtel = makeAirtelAccount($user, 0);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UE188204V1 confirmed. KES50.00 sent to AIRTEL MONEY for account 254731609277 on 1/5/26 at 11:25 AM New M-PESA balance is KES7,500.30. Transaction cost, KES0.00.',
        ])->assertStatus(201)
            ->assertJson([
                'status'  => 'created',
                'subtype' => 'account_transfer',
                'amount'  => 50.0,
                'from'    => 'Mpesa',
                'to'      => 'Airtel Money',
            ]);

        expect(Transfer::withoutGlobalScopes()->where('user_id', $user->id)->count())->toBe(1);

        $transfer = Transfer::withoutGlobalScopes()->where('user_id', $user->id)->first();
        expect($transfer->from_account_id)->toBe($mpesa->id)
            ->and($transfer->to_account_id)->toBe($airtel->id)
            ->and((float) $transfer->amount)->toBe(50.0);
    });

    it('does not create a transaction record for airtel money transfer (uses transfers table)', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user);
        makeAirtelAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UE188204V1 confirmed. KES50.00 sent to AIRTEL MONEY for account 254731609277 on 1/5/26 at 11:25 AM New M-PESA balance is KES7,500.30. Transaction cost, KES0.00.',
        ]);

        expect(
            Transaction::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('is_transaction_fee', false)
                ->count()
        )->toBe(0);
    });

    it('decreases mpesa and increases airtel after transfer', function () {
        $user   = makeWebhookUser();
        $mpesa  = makeMpesaAccount($user, 10000);
        $airtel = makeAirtelAccount($user, 500);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UE188204V1 confirmed. KES200.00 sent to AIRTEL MONEY for account 254731609277 on 1/5/26 at 11:25 AM New M-PESA balance is KES9,800.00. Transaction cost, KES0.00.',
        ]);

        expect((float) $mpesa->fresh()->current_balance)->toBe(9800.0);
        expect((float) $airtel->fresh()->current_balance)->toBe(700.0);
    });

    it('deducts fee from mpesa only — airtel receives full transfer amount', function () {
        $user   = makeWebhookUser();
        $mpesa  = makeMpesaAccount($user, 5000);
        $airtel = makeAirtelAccount($user, 0);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UE188204V2 confirmed. KES500.00 sent to AIRTEL MONEY for account 254731609277 on 1/5/26 at 11:25 AM New M-PESA balance is KES4,490.00. Transaction cost, KES10.00.',
        ]);

        // Mpesa: 5000 - 500 (transfer) - 10 (fee) = 4490
        expect((float) $mpesa->fresh()->current_balance)->toBe(4490.0);
        // Airtel: 0 + 500 (transfer in) — fee stays on mpesa
        expect((float) $airtel->fresh()->current_balance)->toBe(500.0);
    });

    it('detects duplicate airtel money transfer via transfers table', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user, 50000);
        makeAirtelAccount($user);

        $payload = [
            'user_id' => $user->id,
            'sms'     => 'UE188204V1 confirmed. KES50.00 sent to AIRTEL MONEY for account 254731609277 on 1/5/26 at 11:25 AM New M-PESA balance is KES7,500.30. Transaction cost, KES0.00.',
        ];

        webhookPost($payload)->assertStatus(201);
        webhookPost($payload)->assertStatus(200)->assertJson(['status' => 'duplicate']);

        expect(Transfer::withoutGlobalScopes()->where('user_id', $user->id)->count())->toBe(1);
    });

    it('falls back to expense when airtel money account does not exist', function () {
        $user  = makeWebhookUser();
        $mpesa = makeMpesaAccount($user, 5000);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UE188204V1 confirmed. KES50.00 sent to AIRTEL MONEY for account 254731609277 on 1/5/26 at 11:25 AM New M-PESA balance is KES4,950.00. Transaction cost, KES0.00.',
        ])->assertStatus(201)
            ->assertJson(['subtype' => 'account_transfer_fallback']);

        expect(Transfer::withoutGlobalScopes()->where('user_id', $user->id)->count())->toBe(0);
        expect(
            Transaction::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('is_transaction_fee', false)
                ->count()
        )->toBe(1);
        // Balance still decreases as an expense
        expect((float) $mpesa->fresh()->current_balance)->toBe(4950.0);
    });
});

// ── Paybill Transfer: Sanlam MMF ─────────────────────────────────────────────

describe('paybill transfer — sanlam mmf', function () {

    it('creates a transfer from mpesa to sanlam mmf account', function () {
        $user   = makeWebhookUser();
        $mpesa  = makeMpesaAccount($user, 50000);
        $sanlam = makeSanlamAccount($user, 0);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UDR88272FR Confirmed. KES5,000.00 sent to SANLAM UNIT TRUST for account 14468 on 27/4/26 at 9:58 AM New M-PESA balance is KES25,476.30. Transaction cost, KES0.00.',
        ])->assertStatus(201)
            ->assertJson([
                'status'  => 'created',
                'subtype' => 'account_transfer',
                'amount'  => 5000.0,
                'from'    => 'Mpesa',
                'to'      => 'Sanlam MMF',
            ]);

        $transfer = Transfer::withoutGlobalScopes()->where('user_id', $user->id)->first();
        expect($transfer->from_account_id)->toBe($mpesa->id)
            ->and($transfer->to_account_id)->toBe($sanlam->id)
            ->and((float) $transfer->amount)->toBe(5000.0);
    });

    it('decreases mpesa and increases sanlam after transfer', function () {
        $user   = makeWebhookUser();
        $mpesa  = makeMpesaAccount($user, 30000);
        $sanlam = makeSanlamAccount($user, 10000);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UDR88272FR Confirmed. KES5,000.00 sent to SANLAM UNIT TRUST for account 14468 on 27/4/26 at 9:58 AM New M-PESA balance is KES25,000.00. Transaction cost, KES0.00.',
        ]);

        expect((float) $mpesa->fresh()->current_balance)->toBe(25000.0);
        expect((float) $sanlam->fresh()->current_balance)->toBe(15000.0);
    });

    it('creates a fee transaction on mpesa when sanlam transfer has a fee', function () {
        $user  = makeWebhookUser();
        $mpesa = makeMpesaAccount($user, 50000);
        makeSanlamAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UDR88272FR Confirmed. KES5,000.00 sent to SANLAM UNIT TRUST for account 14468 on 27/4/26 at 9:58 AM New M-PESA balance is KES25,471.30. Transaction cost, KES5.00.',
        ]);

        expect(
            Transaction::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('account_id', $mpesa->id)
                ->where('is_transaction_fee', true)
                ->where('amount', 5.0)
                ->exists()
        )->toBeTrue();
    });

    it('falls back to expense when sanlam account does not exist', function () {
        $user  = makeWebhookUser();
        $mpesa = makeMpesaAccount($user, 30000);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UDR88272FR Confirmed. KES5,000.00 sent to SANLAM UNIT TRUST for account 14468 on 27/4/26 at 9:58 AM New M-PESA balance is KES25,000.00. Transaction cost, KES0.00.',
        ])->assertStatus(201)
            ->assertJson(['subtype' => 'account_transfer_fallback']);

        expect(Transfer::withoutGlobalScopes()->where('user_id', $user->id)->count())->toBe(0);
        expect((float) $mpesa->fresh()->current_balance)->toBe(25000.0);
    });
});

// ── Duplicate detection ───────────────────────────────────────────────────────

describe('duplicate detection', function () {

    it('returns duplicate status when same reference is sent twice', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user);

        $payload = [
            'user_id' => $user->id,
            'sms'     => 'FH6KL9O1RE Confirmed. KES500.00 sent to JOHN DOE 0712345678 on 30/4/26 at 8:23 AM. New M-PESA balance is KES13,000.00. Transaction cost, KES29.00.',
        ];

        webhookPost($payload)->assertStatus(201);
        webhookPost($payload)->assertStatus(200)->assertJson([
            'status'    => 'duplicate',
            'reference' => 'FH6KL9O1RE',
        ]);

        expect(
            Transaction::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('is_transaction_fee', false)
                ->count()
        )->toBe(1);
    });

    it('ignores duplicate bank transfer confirmation M-PESA SMS', function () {
        $user = makeWebhookUser();
        makeBankAccount($user);
        makeMpesaAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'Bank to M-PESA transfer of KES 1,000.00 to 254708745191 - SILAS SEJE successfully processed. Transaction Ref ID: 4197QMGO4277. M-PESA Ref ID: UE1882QZ2G',
        ])->assertStatus(201)
            ->assertJson([
                'status'  => 'created',
                'subtype' => 'account_transfer',
                'amount'  => 1000,
                'from'    => 'I&M Bank',
                'to'      => 'Mpesa',
            ]);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UE1882QZ2G Confirmed. You have received KES1,000.00 from IM BANK LIMITED- APP on 1/5/26 at 10:31 PM. New M-PESA balance is Ksh7,226.30. Buy goods with M-PESA.',
        ])->assertStatus(200)
            ->assertJson([
                'status' => 'ignored',
                'reason' => 'Duplicate bank transfer confirmation',
            ]);

        expect(Transfer::withoutGlobalScopes()->where('user_id', $user->id)->count())->toBe(1);
    });

    it('handles bank transfer duplicate detection via mpesa_ref', function () {
        $user = makeWebhookUser();
        makeBankAccount($user);
        makeMpesaAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'Bank to M-PESA transfer of KES 600.00 to 254719685465 - JOHN DOE successfully processed. Transaction Ref ID: 4006DMKD1032. M-PESA Ref ID: UD9O205UFV',
        ])->assertStatus(201);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UD9O205UFV Confirmed. You have received KES600.00 from IM BANK LIMITED- APP on 1/5/26 at 11:03 PM. New M-PESA balance is KES7,226.38.',
        ])->assertStatus(200)
            ->assertJson(['status' => 'ignored']);

        expect(Transfer::withoutGlobalScopes()->where('user_id', $user->id)->count())->toBe(1);
    });
});

// ── Account resolution ────────────────────────────────────────────────────────

describe('account resolution', function () {

    it('returns 404 when no mpesa account exists for user', function () {
        $user = makeWebhookUser();

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'FH6KL9O1RE Confirmed. KES500.00 sent to JOHN DOE 0712345678 on 30/4/26 at 8:23 AM. New M-PESA balance is KES13,000.00. Transaction cost, KES29.00.',
        ])->assertStatus(404)
            ->assertJson(['error' => 'No matching account found']);
    });

    it('returns 404 when no bank account exists for I&M outgoing SMS', function () {
        $user = makeWebhookUser();
        // No bank account — mpesa alone is not enough for a bank-sourced transfer
        makeMpesaAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'Bank to M-PESA transfer of KES 600.00 to 254719685465 - JOHN DOE successfully processed. Transaction Ref ID: 4006DMKD1032. M-PESA Ref ID: UD9O205UFV',
        ])->assertStatus(404)
            ->assertJson(['error' => 'Bank account not found']);
    });

    it('returns 404 when no bank account exists for Bank→Airtel SMS', function () {
        $user = makeWebhookUser();
        makeAirtelAccount($user);
        // No bank account

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'Bank to Airtel Money Transfer of KES 250.00 to 254731609277 successfully processed. Transaction Ref ID:REFBA001. Airtel Money Ref ID:REFBA002.',
        ])->assertStatus(404)
            ->assertJson(['error' => 'Bank account not found']);
    });
});

// ── I&M: Bank → Mpesa ─────────────────────────────────────────────────────────

describe('I&M bank to mpesa', function () {

    it('creates a transfer with bank as source and mpesa as destination', function () {
        $user  = makeWebhookUser();
        $bank  = makeBankAccount($user);
        $mpesa = makeMpesaAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'Bank to M-PESA transfer of KES 600.00 to 254719685465 - OGACHI OBONGO DAVID successfully processed. Transaction Ref ID: 4006DMKD1032. M-PESA Ref ID: UD9O205UFV',
        ])->assertStatus(201)
            ->assertJson([
                'status'  => 'created',
                'subtype' => 'account_transfer',
                'amount'  => 600,
                'from'    => 'I&M Bank',
                'to'      => 'Mpesa',
            ]);

        expect(Transfer::withoutGlobalScopes()->where('user_id', $user->id)->count())->toBe(1);
        expect(
            Transaction::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('is_transaction_fee', false)
                ->count()
        )->toBe(0);

        $transfer = Transfer::withoutGlobalScopes()->where('user_id', $user->id)->first();
        expect($transfer->from_account_id)->toBe($bank->id)
            ->and($transfer->to_account_id)->toBe($mpesa->id);
    });

    it('decreases bank balance and increases mpesa balance', function () {
        $user  = makeWebhookUser();
        $bank  = makeBankAccount($user, 20000);
        $mpesa = makeMpesaAccount($user, 5000);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'Bank to M-PESA transfer of KES 2,000.00 to 254719685465 - JOHN DOE successfully processed. Transaction Ref ID: REFBM001. M-PESA Ref ID: REFBM002',
        ]);

        expect((float) $bank->fresh()->current_balance)->toBe(18000.0);
        expect((float) $mpesa->fresh()->current_balance)->toBe(7000.0);
    });

    it('uses the bank transaction reference not the mpesa reference', function () {
        $user = makeWebhookUser();
        makeBankAccount($user);
        makeMpesaAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'Bank to M-PESA transfer of KES 600.00 to 254719685465 - JOHN DOE successfully processed. Transaction Ref ID: 4006DMKD1032. M-PESA Ref ID: UD9O205UFV',
        ]);

        $transfer = Transfer::withoutGlobalScopes()->where('user_id', $user->id)->first();
        expect($transfer->description)->toContain('4006DMKD1032');
    });

    it('does not double-count when the M-PESA confirmation SMS arrives after the bank SMS', function () {
        $user  = makeWebhookUser();
        $bank  = makeBankAccount($user, 20000);
        $mpesa = makeMpesaAccount($user, 5000);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'Bank to M-PESA transfer of KES 2,000.00 to 254719685465 - JOHN DOE successfully processed. Transaction Ref ID: REFBM003. M-PESA Ref ID: REFBM004',
        ])->assertStatus(201);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'REFBM004 Confirmed. You have received KES2,000.00 from IM BANK LIMITED- APP on 1/5/26 at 10:31 PM. New M-PESA balance is Ksh7,000.00.',
        ])->assertStatus(200)->assertJson(['status' => 'ignored']);

        // Balances must reflect exactly one transfer
        expect((float) $bank->fresh()->current_balance)->toBe(18000.0);
        expect((float) $mpesa->fresh()->current_balance)->toBe(7000.0);
        expect(Transfer::withoutGlobalScopes()->where('user_id', $user->id)->count())->toBe(1);
    });
});

// ── I&M: Bank → Airtel Money ──────────────────────────────────────────────────

describe('I&M bank to airtel money', function () {

    it('creates a transfer with bank as source and airtel as destination', function () {
        $user   = makeWebhookUser();
        $bank   = makeBankAccount($user);
        $airtel = makeAirtelAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'Bank to Airtel Money Transfer of KES 250.00 to 254731609277 successfully processed. Transaction Ref ID:888660788069. Airtel Money Ref ID:Z3KVKUL3OKA.',
        ])->assertStatus(201)
            ->assertJson([
                'status'  => 'created',
                'subtype' => 'account_transfer',
                'amount'  => 250.0,
                'from'    => 'I&M Bank',
                'to'      => 'Airtel Money',
            ]);

        $transfer = Transfer::withoutGlobalScopes()->where('user_id', $user->id)->first();
        expect($transfer->from_account_id)->toBe($bank->id)
            ->and($transfer->to_account_id)->toBe($airtel->id);
    });

    it('decreases bank balance and increases airtel balance', function () {
        $user   = makeWebhookUser();
        $bank   = makeBankAccount($user, 10000);
        $airtel = makeAirtelAccount($user, 500);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'Bank to Airtel Money Transfer of KES 1,000.00 to 254731609277 successfully processed. Transaction Ref ID:REFBA003. Airtel Money Ref ID:REFBA004.',
        ]);

        expect((float) $bank->fresh()->current_balance)->toBe(9000.0);
        expect((float) $airtel->fresh()->current_balance)->toBe(1500.0);
    });

    it('falls back to expense on bank when airtel account does not exist', function () {
        $user = makeWebhookUser();
        $bank = makeBankAccount($user, 10000);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'Bank to Airtel Money Transfer of KES 250.00 to 254731609277 successfully processed. Transaction Ref ID:REFBA005. Airtel Money Ref ID:REFBA006.',
        ])->assertStatus(201)
            ->assertJson(['subtype' => 'account_transfer_fallback']);

        expect(Transfer::withoutGlobalScopes()->where('user_id', $user->id)->count())->toBe(0);
        // Recorded as expense on bank account
        expect((float) $bank->fresh()->current_balance)->toBe(9750.0);
    });
});

// ── I&M: ATM Withdrawal ───────────────────────────────────────────────────────

describe('I&M ATM withdrawal', function () {

    it('creates a transfer from bank to cash', function () {
        $user = makeWebhookUser();
        $bank = makeBankAccount($user, 50000);
        $cash = makeCashAccount($user, 0);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'Dear SILAS, you withdrew KES 30,000.00 on 2026-04-25 09:59:05 at I&M BANK KENYATTA KE 2 using I&M 5477********9433.',
        ])->assertStatus(201)
            ->assertJson([
                'status'  => 'created',
                'subtype' => 'atm_withdrawal',
                'amount'  => 30000.0,
                'fee'     => 37.95,
            ]);

        expect(Transfer::withoutGlobalScopes()->where('user_id', $user->id)->count())->toBe(1);

        $transfer = Transfer::withoutGlobalScopes()->where('user_id', $user->id)->first();
        expect($transfer->from_account_id)->toBe($bank->id)
            ->and($transfer->to_account_id)->toBe($cash->id)
            ->and((float) $transfer->amount)->toBe(30000.0);
    });

    it('creates an ATM fee transaction on the bank account', function () {
        $user = makeWebhookUser();
        $bank = makeBankAccount($user, 50000);
        makeCashAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'Dear SILAS, you withdrew KES 30,000.00 on 2026-04-25 09:59:05 at I&M BANK KENYATTA KE 2 using I&M 5477********9433.',
        ]);

        expect(
            Transaction::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('account_id', $bank->id)
                ->where('amount', 37.95)
                ->where('is_transaction_fee', true)
                ->exists()
        )->toBeTrue();
    });

    it('returns 404 when cash account is missing', function () {
        $user = makeWebhookUser();
        makeBankAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'Dear SILAS, you withdrew KES 5,000.00 on 2026-04-25 09:59:05 at I&M BANK CBD using I&M 5477********9433.',
        ])->assertStatus(404)
            ->assertJson(['error' => 'Bank or cash account not found']);
    });

    it('decreases bank by amount plus fee and increases cash by amount only', function () {
        $user = makeWebhookUser();
        $bank = makeBankAccount($user, 50000);
        $cash = makeCashAccount($user, 1000);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'Dear SILAS, you withdrew KES 10,000.00 on 2026-04-25 09:59:05 at I&M BANK WESTLANDS using I&M 5477********9433.',
        ]);

        // Bank: 50000 - 10000 (transfer) - 37.95 (fee) = 39962.05
        expect((float) $bank->fresh()->current_balance)->toBe(39962.05);
        // Cash: 1000 + 10000 — fee stays on bank
        expect((float) $cash->fresh()->current_balance)->toBe(11000.0);
    });
});

// ── Mpesa: Inter-account transfer (Airtel → Mpesa) ────────────────────────────

describe('mpesa inter-account transfer', function () {

    it('creates a transfer record for received airtel money', function () {
        $user   = makeWebhookUser();
        $mpesa  = makeMpesaAccount($user, 1000);
        $airtel = makeAirtelAccount($user, 2000);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UE18820MJ1 Confirmed. You have received KES10.00 from AIRTEL MONEY - Silas Seje 731609277 on 1/5/26 at 1:12 PM New M-PESA balance is KES7,448.30.',
        ])->assertStatus(201)
            ->assertJson([
                'status'  => 'created',
                'subtype' => 'account_transfer',
                'type'    => 'transfer',
                'amount'  => 10.0,
                'from'    => 'Airtel Money',
                'to'      => 'Mpesa',
            ]);

        expect(Transfer::withoutGlobalScopes()->where('user_id', $user->id)->count())->toBe(1);
    });

    it('decreases airtel and increases mpesa', function () {
        $user   = makeWebhookUser();
        $mpesa  = makeMpesaAccount($user, 1000);
        $airtel = makeAirtelAccount($user, 2000);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UE18820MJ1 Confirmed. You have received KES500.00 from AIRTEL MONEY - Silas Seje 731609277 on 1/5/26 at 1:12 PM New M-PESA balance is KES1,500.00.',
        ]);

        expect((float) $mpesa->fresh()->current_balance)->toBe(1500.0);
        expect((float) $airtel->fresh()->current_balance)->toBe(1500.0);
    });

    it('falls back to income when airtel account does not exist', function () {
        $user  = makeWebhookUser();
        $mpesa = makeMpesaAccount($user, 1000);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UE18820MJ1 Confirmed. You have received KES200.00 from AIRTEL MONEY - Silas Seje 731609277 on 1/5/26 at 1:12 PM New M-PESA balance is KES1,200.00.',
        ])->assertStatus(201)
            ->assertJson(['subtype' => 'account_transfer_fallback']);

        expect(Transfer::withoutGlobalScopes()->where('user_id', $user->id)->count())->toBe(0);
        expect((float) $mpesa->fresh()->current_balance)->toBe(1200.0);
    });
});

// ── Mpesa: Withdrawal (Agent) ─────────────────────────────────────────────────

describe('mpesa withdrawal', function () {

    it('creates a transaction for mpesa withdrawal', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UDU882LSDZ Confirmed. KES500.00 withdrawn from Agent on 30/4/26 at 6:34 PM. New M-PESA balance is KES5,790.30. Transaction cost, KES13.00.',
        ])->assertStatus(201)
            ->assertJson([
                'status'  => 'created',
                'subtype' => 'withdrawal',
                'amount'  => 500.0,
                'fee'     => 13.0,
            ]);
    });

    it('decreases mpesa balance by amount plus fee on withdrawal', function () {
        $user  = makeWebhookUser();
        $mpesa = makeMpesaAccount($user, 10000);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UDU882LSDZ Confirmed. KES500.00 withdrawn from Agent on 30/4/26 at 6:34 PM. New M-PESA balance is KES9,487.00. Transaction cost, KES13.00.',
        ]);

        // 10000 - 500 (main) - 13 (fee) = 9487
        expect((float) $mpesa->fresh()->current_balance)->toBe(9487.0);
    });
});

// ── Mpesa: Pochi la Biashara ───────────────────────────────────────────────────

describe('mpesa pochi la biashara', function () {

    it('creates a transaction for pochi payment', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UDU882J1IZ Confirmed. KES50.00 sent to MAGDALENE WAMBUI on 30/4/26 at 6:34 AM. New M-PESA balance is KES19,425.30. Transaction cost, KES0.00.',
        ])->assertStatus(201)
            ->assertJson([
                'status'  => 'created',
                'subtype' => 'pochi',
                'amount'  => 50.0,
            ]);
    });

    it('categorises pochi as Other Expenses', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UDU882J1IZ Confirmed. KES50.00 sent to MAGDALENE WAMBUI on 30/4/26 at 6:34 AM. New M-PESA balance is KES19,425.30. Transaction cost, KES0.00.',
        ]);

        $tx = Transaction::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('is_transaction_fee', false)
            ->first();

        expect($tx->category->name)->toBe('Other Expenses');
    });

    it('decreases mpesa balance on pochi payment', function () {
        $user  = makeWebhookUser();
        $mpesa = makeMpesaAccount($user, 5000);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UDU882J1IZ Confirmed. KES50.00 sent to MAGDALENE WAMBUI on 30/4/26 at 6:34 AM. New M-PESA balance is KES4,950.00. Transaction cost, KES0.00.',
        ]);

        expect((float) $mpesa->fresh()->current_balance)->toBe(4950.0);
    });
});

// ── Auto category creation ────────────────────────────────────────────────────

describe('category auto-creation', function () {

    it('creates a new category when it does not exist', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user);

        expect(Category::where('user_id', $user->id)->where('name', 'Airtime & Data')->exists())->toBeFalse();

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UDU882MWE1 confirmed.You bought KES50.00 of airtime on 30/4/26 at 10:17 PM.New M-PESA balance is KES6,716.30. Transaction cost, KES0.00.',
        ]);

        expect(Category::where('user_id', $user->id)->where('name', 'Airtime & Data')->exists())->toBeTrue();
    });

    it('reuses an existing category instead of creating a duplicate', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user);

        Category::factory()->create(['user_id' => $user->id, 'name' => 'Airtime & Data', 'type' => 'expense']);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UDU882MWE1 confirmed.You bought KES50.00 of airtime on 30/4/26 at 10:17 PM.New M-PESA balance is KES6,716.30. Transaction cost, KES0.00.',
        ]);

        expect(
            Category::where('user_id', $user->id)->where('name', 'Airtime & Data')->count()
        )->toBe(1);
    });

    it('creates Transaction Fees category automatically when a fee is charged', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'FH6KL9O1RE Confirmed. KES500.00 sent to JOHN DOE 0712345678 on 30/4/26 at 8:23 AM. New M-PESA balance is KES13,000.00. Transaction cost, KES29.00.',
        ]);

        expect(
            Category::where('user_id', $user->id)->where('name', 'Transaction Fees')->exists()
        )->toBeTrue();
    });
});
