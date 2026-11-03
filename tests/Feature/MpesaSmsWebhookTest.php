<?php
// tests/Feature/MpesaSmsWebhookTest.php

use App\Models\Account;
use App\Models\Category;
use App\Models\Referrer;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use App\Services\TransactionRecorder;
use App\Services\TransactionService;
use Illuminate\Database\UniqueConstraintViolationException;
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

function makeEticaAccount(User $user, float $balance = 0): Account
{
    return Account::factory()->create([
        'user_id'         => $user->id,
        'type'            => 'savings',
        'name'            => 'Etica Savings',
        'current_balance' => $balance,
        'initial_balance' => $balance,
        'is_active'       => true,
    ]);
}

/** PesaLink SMS to the Etica account at Equity (amount is a display string, e.g. "5,000.00"). */
function pesalinkEticaSms(string $ref = 'PLK0001', string $amount = '5,000.00'): string
{
    return "Pesalink transfer of KES {$amount} to EQUITY BANK KENYA A/c 0180283951027 on 29/05/2026 12:38 processed successfully. Transaction Ref ID: {$ref}";
}

/** Direct Transaction insert for Account::updateBalance() tests. Adjust columns to your schema. */
function makeTx(Account $account, Category $category, float $amount, ?string $valueDate = null, ?string $date = null): Transaction
{
    return Transaction::forceCreate([
        'user_id'     => $account->user_id,
        'account_id'  => $account->id,
        'category_id' => $category->id,
        'amount'      => $amount,
        'date'        => $date ?? now()->toDateString(),
        'value_date'  => $valueDate,
        'description' => 'test ' . uniqid(),
    ]);
}

function makeSavingsTransfer(Account $to, Account $from, float $amount, ?string $valueDate): Transfer
{
    return Transfer::forceCreate([
        'user_id'         => $to->user_id,
        'from_account_id' => $from->id,
        'to_account_id'   => $to->id,
        'amount'          => $amount,
        'date'            => now(),
        'value_date'      => $valueDate,
        'description'     => 'test transfer ' . uniqid(),
    ]);
}

function cashbackSms(string $ref = 'Q3PH3911CC5'): string
{
    return "Ref {$ref}. Ksh 5  moved to your wallet  0731xxx277 on 01/08/26 at 08:13 AM Bal: Ksh 0.65. Airtel Money Wallet Bal: Ksh 14452.0";
}

function makeAirtelFee(User $user, Account $airtel, float $amount, string $date): Transaction
{
    $cat = Category::factory()->create(['user_id' => $user->id, 'name' => 'Transaction Fees', 'type' => 'expense']);

    return Transaction::forceCreate([
        'user_id'            => $user->id,
        'account_id'         => $airtel->id,
        'category_id'        => $cat->id,
        'amount'             => $amount,
        'date'               => $date,
        'description'        => 'Transaction fee for TESTFEE',
        'payment_method'     => 'Airtel Money',
        'is_transaction_fee' => true,
    ]);
}

function makeReferrer(User $user, string $name = 'Phides', ?string $contact = '0713123724'): Referrer
{
    // Add any other required columns for your schema
    return Referrer::forceCreate([
        'user_id'   => $user->id,
        'name'      => $name,
        'contact'   => $contact,
        'is_active' => true,
    ]);
}

function makeFloatAccount(User $user, Referrer $referrer, float $balance = 10000): Account
{
    return Account::factory()->create([
        'user_id'         => $user->id,
        'type'            => 'referrer_float',
        'name'            => $referrer->name . ' Float',
        'referrer_id'     => $referrer->id,
        'current_balance' => $balance,
        'initial_balance' => $balance,
        'is_active'       => true,
    ]);
}

function referrerSms(string $sender = 'PHIDES NJERU 0713***724', string $amount = '1,000.00'): string
{
    return "UDG880ZSXQ Confirmed. You have received KES{$amount} from {$sender} on 16/4/26 at 2:00 PM. New M-PESA balance is KES6,000.00.";
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

    // Renamed: previously said "Groceries" but asserted Other Expenses
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

    it('maps Safaricom paybill to Airtime & Data', function () {
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

        expect($tx->category->name)->toBe('Airtime & Data');
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
            'sms'     => 'UE188204V2 confirmed. KES500.00 sent to AIRTEL MONEY for account 254731609277 on 1/5/26 at 11:25 AM New M-PESA balance is KES4,500.00. Transaction cost, KES0.00.',
        ]);

        // Mpesa: 5000 - 500 (transfer) = 4500 (no fee charged)
        expect((float) $mpesa->fresh()->current_balance)->toBe(4500.0);
        // Airtel: 0 + 500 (transfer in)
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

// ── Etica interest gate: regression for the 1970-01-01 sentinel bug ───────────
// Etica is the only account where the webhook sets value_date, so it is the
// real regression surface for Account::updateBalance()'s interest gate.

describe('etica interest gate — no interest history', function () {

    it('credits a new etica account after a PesaLink deposit', function () {
        $user = makeWebhookUser();
        makeBankAccount($user, 100000);
        $etica = makeEticaAccount($user, 0);

        webhookPost(['user_id' => $user->id, 'sms' => pesalinkEticaSms()])
            ->assertStatus(201)
            ->assertJson(['subtype' => 'pesalink_to_savings']);

        $transfer = Transfer::withoutGlobalScopes()->where('user_id', $user->id)->first();
        expect($transfer->value_date)->not->toBeNull();

        // With the old 1970 sentinel this stayed at 0 forever
        expect((float) $etica->fresh()->current_balance)->toBe(5000.0);
    });

    it('credits a new etica account after an M-PESA paybill deposit', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user, 30000);
        $etica = makeEticaAccount($user, 0);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UDR88272FR Confirmed. KES5,000.00 sent to ETICA CAPITAL LTD for account 14468 on 27/4/26 at 9:58 AM New M-PESA balance is KES25,000.00. Transaction cost, KES0.00.',
        ])->assertStatus(201)
            ->assertJson(['subtype' => 'account_transfer', 'to' => 'Etica Savings']);

        expect(Transfer::withoutGlobalScopes()->where('user_id', $user->id)->first()->value_date)->not->toBeNull();
        expect((float) $etica->fresh()->current_balance)->toBe(5000.0);
    });
});

// ── Savings balance gating (Account::updateBalance) ──────────────────────────
// "Released" uses a date 2 days back and "held" 2 days ahead so the tests
// don't flip around midnight if app and DB timezones differ.

describe('savings balance gating', function () {

    it('releases past-dated transfers when there is no interest history', function () {
        $user    = makeWebhookUser();
        $mpesa   = makeMpesaAccount($user);
        $savings = makeSanlamAccount($user, 0);

        makeSavingsTransfer($savings, $mpesa, 1000, now()->subDays(2)->toDateString());
        $savings->updateBalance();

        expect((float) $savings->fresh()->current_balance)->toBe(1000.0);
    });

    it('holds back transfers with a future value date when there is no interest history', function () {
        $user    = makeWebhookUser();
        $mpesa   = makeMpesaAccount($user);
        $savings = makeSanlamAccount($user, 0);

        makeSavingsTransfer($savings, $mpesa, 1000, now()->addDays(2)->toDateString());
        $savings->updateBalance();

        expect((float) $savings->fresh()->current_balance)->toBe(0.0);
    });

    it('holds back deposits newer than the last recorded interest date', function () {
        $user     = makeWebhookUser();
        $mpesa    = makeMpesaAccount($user);
        $savings  = makeSanlamAccount($user, 0);
        $interest = Category::factory()->create(['user_id' => $user->id, 'name' => 'Interest', 'type' => 'income']);

        // Interest posted yesterday → gate is yesterday
        makeTx($savings, $interest, 10, null, now()->subDay()->toDateString());

        makeSavingsTransfer($savings, $mpesa, 1000, now()->toDateString());          // after gate → held
        makeSavingsTransfer($savings, $mpesa, 500, now()->subDay()->toDateString()); // on gate → released

        $savings->updateBalance();

        // 10 interest + 500 released; the 1000 is still pending
        expect((float) $savings->fresh()->current_balance)->toBe(510.0);
    });

    it('excludes pending income deposits but never the interest itself', function () {
        $user     = makeWebhookUser();
        $savings  = makeSanlamAccount($user, 0);
        $interest = Category::factory()->create(['user_id' => $user->id, 'name' => 'Interest', 'type' => 'income']);
        $income   = Category::factory()->create(['user_id' => $user->id, 'name' => 'Side Income', 'type' => 'income']);

        makeTx($savings, $interest, 10, null, now()->subDay()->toDateString());
        makeTx($savings, $income, 2000, now()->toDateString()); // value_date > last interest → pending

        $savings->updateBalance();

        expect((float) $savings->fresh()->current_balance)->toBe(10.0);
    });

    it('releases past-dated transfers into non-savings accounts', function () {
        $user  = makeWebhookUser();
        $bank  = makeBankAccount($user, 0);
        $mpesa = makeMpesaAccount($user);

        makeSavingsTransfer($bank, $mpesa, 700, now()->subDays(2)->toDateString());
        $bank->updateBalance();

        expect((float) $bank->fresh()->current_balance)->toBe(700.0);
    });
});

// ── PesaLink ──────────────────────────────────────────────────────────────────
// Fee for KES 5,000: 44 * 1.15 = 50.60

describe('pesalink', function () {

    it('moves money bank → etica and charges the fee to the bank', function () {
        $user = makeWebhookUser();
        $bank = makeBankAccount($user, 100000);
        $acct = makeEticaAccount($user, 0);

        webhookPost(['user_id' => $user->id, 'sms' => pesalinkEticaSms()])
            ->assertStatus(201)
            ->assertJson([
                'status'  => 'created',
                'subtype' => 'pesalink_to_savings',
                'amount'  => 5000.0,
                'fee'     => 50.6,
                'from'    => 'I&M Bank',
                'to'      => 'Etica Savings',
            ]);

        $t = Transfer::withoutGlobalScopes()->where('user_id', $user->id)->first();
        expect($t->from_account_id)->toBe($bank->id)->and($t->to_account_id)->toBe($acct->id);
        expect($t->mpesa_reference)->toBe('PLK0001');

        expect(
            Transaction::withoutGlobalScopes()
                ->where('account_id', $bank->id)
                ->where('is_transaction_fee', true)
                ->where('amount', 50.6)
                ->exists()
        )->toBeTrue();

        expect((float) $bank->fresh()->current_balance)->toEqualWithDelta(94949.4, 0.001);
    });

    it('returns 404 when there is no bank account', function () {
        $user = makeWebhookUser();
        makeEticaAccount($user);

        webhookPost(['user_id' => $user->id, 'sms' => pesalinkEticaSms()])
            ->assertStatus(404)
            ->assertJson(['error' => 'Bank account not found']);
    });

    it('falls back to a bank expense when the etica account is missing', function () {
        $user = makeWebhookUser();
        $bank = makeBankAccount($user, 100000);

        webhookPost(['user_id' => $user->id, 'sms' => pesalinkEticaSms()])
            ->assertStatus(201)
            ->assertJson(['subtype' => 'pesalink_fallback']);

        expect(Transfer::withoutGlobalScopes()->count())->toBe(0);
        expect(Transaction::withoutGlobalScopes()->where('account_id', $bank->id)->count())->toBe(2); // expense + fee
        expect((float) $bank->fresh()->current_balance)->toEqualWithDelta(94949.4, 0.001);
    });

    it('detects a duplicate PesaLink SMS', function () {
        $user = makeWebhookUser();
        makeBankAccount($user, 100000);
        makeEticaAccount($user);

        webhookPost(['user_id' => $user->id, 'sms' => pesalinkEticaSms()])->assertStatus(201);
        webhookPost(['user_id' => $user->id, 'sms' => pesalinkEticaSms()])
            ->assertStatus(200)
            ->assertJson(['status' => 'duplicate', 'reference' => 'PLK0001']);

        expect(Transfer::withoutGlobalScopes()->count())->toBe(1);
    });

    it('treats PesaLink to another bank as an expense, not a transfer', function () {
        $user = makeWebhookUser();
        $bank = makeBankAccount($user, 100000);
        makeEticaAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'Pesalink transfer of KES 5,000.00 to KCB BANK A/c 1234567890 on 29/05/2026 12:38 processed successfully. Transaction Ref ID: PLK0002',
        ])->assertStatus(201)
            ->assertJson(['type' => 'expense', 'subtype' => 'pesalink_outgoing']);

        expect(Transfer::withoutGlobalScopes()->count())->toBe(0);
        expect((float) $bank->fresh()->current_balance)->toEqualWithDelta(94949.4, 0.001);
    });
});

// ── Airtel cashback ───────────────────────────────────────────────────────────
// Cashback is netted against the nearest preceding fee within 30 days, and that
// fee must be at least as large as the cashback.

describe('airtel cashback', function () {

    it('reduces the nearest eligible fee by the cashback amount', function () {
        $user   = makeWebhookUser();
        $airtel = makeAirtelAccount($user, 1000);
        $fee    = makeAirtelFee($user, $airtel, 10, '2026-07-20');

        webhookPost(['user_id' => $user->id, 'sms' => cashbackSms()])
            ->assertStatus(200)
            ->assertJson([
                'status'       => 'cashback_applied',
                'original_fee' => 10.0,
                'cashback'     => 5.0,
                'new_fee'      => 5.0,
            ]);

        $fee->refresh();
        expect((float) $fee->amount)->toBe(5.0)
            ->and($fee->description)->toContain('Q3PH3911CC5');
        expect((float) $airtel->fresh()->current_balance)->toBe(995.0);
    });

    it('is idempotent — a repeat cashback SMS is a duplicate', function () {
        $user   = makeWebhookUser();
        $airtel = makeAirtelAccount($user, 1000);
        $fee    = makeAirtelFee($user, $airtel, 10, '2026-07-20');

        webhookPost(['user_id' => $user->id, 'sms' => cashbackSms()])->assertStatus(200);
        webhookPost(['user_id' => $user->id, 'sms' => cashbackSms()])
            ->assertJson(['status' => 'duplicate']);

        expect((float) $fee->fresh()->amount)->toBe(5.0);
    });

    it('records income when the only fee is smaller than the cashback', function () {
        $user   = makeWebhookUser();
        $airtel = makeAirtelAccount($user, 1000);
        makeAirtelFee($user, $airtel, 3, '2026-07-20');

        webhookPost(['user_id' => $user->id, 'sms' => cashbackSms()])
            ->assertStatus(201)
            ->assertJson(['type' => 'income', 'subtype' => 'airtelcashback']);

        expect((float) $airtel->fresh()->current_balance)->toBe(1002.0); // 1000 + 5 - 3
    });

    it('records income when the fee is older than 30 days', function () {
        $user   = makeWebhookUser();
        $airtel = makeAirtelAccount($user, 1000);
        makeAirtelFee($user, $airtel, 10, '2026-06-01');

        webhookPost(['user_id' => $user->id, 'sms' => cashbackSms()])
            ->assertStatus(201)
            ->assertJson(['type' => 'income']);

        expect((float) $airtel->fresh()->current_balance)->toBe(995.0); // 1000 + 5 - 10
    });

    it('returns 404 when there is no airtel account', function () {
        $user = makeWebhookUser();

        webhookPost(['user_id' => $user->id, 'sms' => cashbackSms()])
            ->assertStatus(404)
            ->assertJson(['error' => 'No matching account found']);
    });
});

// ── Referrer float remittance ─────────────────────────────────────────────────

describe('referrer float remittance', function () {

    it('records money from a referrer as a float → mpesa transfer, not income', function () {
        $user     = makeWebhookUser();
        $mpesa    = makeMpesaAccount($user, 5000);
        $referrer = makeReferrer($user);
        $float    = makeFloatAccount($user, $referrer, 10000);
        $fee      = (float) app(TransactionService::class)->mpesaSendMoneyFee(1000.0);

        webhookPost(['user_id' => $user->id, 'sms' => referrerSms()])
            ->assertStatus(201)
            ->assertJson([
                'status'          => 'created',
                'subtype'         => 'referrer_float_remittance',
                'amount'          => 1000.0,
                'interest_linked' => 0.0,
                'principal'       => 1000.0,
                'from'            => 'Phides Float',
                'to'              => 'Mpesa',
            ]);

        $t = Transfer::withoutGlobalScopes()->where('user_id', $user->id)->first();
        expect($t->from_account_id)->toBe($float->id)->and($t->to_account_id)->toBe($mpesa->id);

        // fee is charged to the float and linked to the transfer
        expect(
            Transaction::withoutGlobalScopes()
                ->where('account_id', $float->id)
                ->where('is_transaction_fee', true)
                ->where('transfer_id', $t->id)
                ->exists()
        )->toBeTrue();

        expect((float) $mpesa->fresh()->current_balance)->toBe(6000.0);
        expect((float) $float->fresh()->current_balance)->toEqualWithDelta(9000.0 - $fee, 0.001);
    });

    it('still records the remittance when it exceeds the recorded float balance', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user, 5000);
        $referrer = makeReferrer($user);
        makeFloatAccount($user, $referrer, 100);

        webhookPost(['user_id' => $user->id, 'sms' => referrerSms()])->assertStatus(201);

        expect(Transfer::withoutGlobalScopes()->count())->toBe(1);
    });

    it('treats a sender with a mismatched masked phone as ordinary income', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user, 5000);
        makeFloatAccount($user, makeReferrer($user));

        webhookPost(['user_id' => $user->id, 'sms' => referrerSms('PHIDES NJERU 0799***111')])
            ->assertStatus(201)
            ->assertJson(['subtype' => 'receive_money', 'type' => 'income']);

        expect(Transfer::withoutGlobalScopes()->count())->toBe(0);
    });

    it('does not match a name that only shares a prefix (whole-word match)', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user, 5000);
        makeFloatAccount($user, makeReferrer($user));

        webhookPost(['user_id' => $user->id, 'sms' => referrerSms('PHIDESON KAMAU 0713***724')])
            ->assertJson(['subtype' => 'receive_money']);

        expect(Transfer::withoutGlobalScopes()->count())->toBe(0);
    });

    it('treats a referrer without a float account as ordinary income', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user, 5000);
        makeReferrer($user); // no float account

        webhookPost(['user_id' => $user->id, 'sms' => referrerSms()])
            ->assertJson(['subtype' => 'receive_money']);
    });

    it('returns 404 when the mpesa account is missing', function () {
        $user = makeWebhookUser();
        makeFloatAccount($user, makeReferrer($user));

        webhookPost(['user_id' => $user->id, 'sms' => referrerSms()])
            ->assertStatus(404)
            ->assertJson(['error' => 'Referrer float or Mpesa account not found']);
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

    it('does not double-count when the M-PESA confirmation SMS arrives after the bank SMS', function () {
        $user  = makeWebhookUser();
        $bank  = makeBankAccount($user, 20000);
        $mpesa = makeMpesaAccount($user, 5000);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'Bank to M-PESA transfer of KES 2,000.00 to 254708745191 - SILAS SEJE successfully processed. Transaction Ref ID: REFBM003. M-PESA Ref ID: REFBM004',
        ])->assertStatus(201);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'REFBM004 Confirmed. You have received KES2,000.00 from IM BANK LIMITED- APP on 1/5/26 at 10:31 PM. New M-PESA balance is Ksh5,000.00.',
        ])->assertStatus(200)->assertJson(['status' => 'duplicate']);

        expect((float) $bank->fresh()->current_balance)->toBe(18000.0);
        expect((float) $mpesa->fresh()->current_balance)->toBe(7000.0); // first leg did everything
        expect(Transfer::withoutGlobalScopes()->where('user_id', $user->id)->count())->toBe(1);
    });

    it('returns duplicate when the recorder hits a unique constraint (race-condition safety net)', function () {
        $user = makeWebhookUser();
        makeMpesaAccount($user);

        // 4-argument form (Laravel 10.x+). Drop the first argument on older versions.
        $this->mock(TransactionRecorder::class, function ($mock) {
            $mock->shouldReceive('record')->once()->andThrow(
                new UniqueConstraintViolationException('mysql', 'insert into transactions ...', [], new \Exception('dup'))
            );
        });

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'FH6KL9O1RE Confirmed. KES500.00 sent to JOHN DOE 0712345678 on 30/4/26 at 8:23 AM. New M-PESA balance is KES13,000.00. Transaction cost, KES29.00.',
        ])->assertStatus(200)->assertJson([
            'status'    => 'duplicate',
            'reference' => 'FH6KL9O1RE',
            'note'      => 'Caught by unique constraint',
        ]);
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
            ->assertJson(['error' => 'No matching account found']);
    });

    it('returns 404 when no bank account exists for Bank→Airtel SMS', function () {
        $user = makeWebhookUser();
        makeAirtelAccount($user);
        // No bank account

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'Bank to Airtel Money Transfer of KES 250.00 to 254731609277 successfully processed. Transaction Ref ID:REFBA001. Airtel Money Ref ID:REFBA002.',
        ])->assertStatus(404)
            ->assertJson(['error' => 'Bank or Airtel Money account not found']);
    });
});

// ── I&M: Bank → Mpesa ─────────────────────────────────────────────────────────

describe('I&M bank to mpesa', function () {

    it('creates a transfer with bank as source and mpesa as destination', function () {
        $user  = makeWebhookUser();
        $bank  = makeBankAccount($user);
        $mpesa = makeMpesaAccount($user);

        // Use self-transfer phone number to trigger transfer routing
        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'Bank to M-PESA transfer of KES 600.00 to 254708745191 - SILAS SEJE successfully processed. Transaction Ref ID: 4006DMKD1032. M-PESA Ref ID: UD9O205UFV',
        ])->assertStatus(201)
            ->assertJson([
                'status'  => 'created',
                'subtype' => 'bank_to_mpesa_self',
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

        // Use self-transfer phone number
        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'Bank to M-PESA transfer of KES 2,000.00 to 254708745191 - SILAS SEJE successfully processed. Transaction Ref ID: REFBM001. M-PESA Ref ID: REFBM002',
        ]);

        expect((float) $bank->fresh()->current_balance)->toBe(18000.0);
        expect((float) $mpesa->fresh()->current_balance)->toBe(7000.0);
    });

    // Renamed: the parser uses the M-PESA ref ($m[5]), not the bank's, and this asserts that
    it('uses the M-PESA reference for the transfer', function () {
        $user = makeWebhookUser();
        makeBankAccount($user);
        makeMpesaAccount($user);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'Bank to M-PESA transfer of KES 600.00 to 254708745191 - SILAS SEJE successfully processed. Transaction Ref ID: 4006DMKD1032. M-PESA Ref ID: UD9O205UFV',
        ]);

        $transfer = Transfer::withoutGlobalScopes()->where('user_id', $user->id)->first();
        expect($transfer)->not->toBeNull();
        expect($transfer->description)->toContain('UD9O205UFV');
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
                'subtype' => 'bank_to_airtel_self',
                'amount'  => 250,
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

    it('returns 404 and creates no transfer when airtel account does not exist', function () {
        $user = makeWebhookUser();
        makeBankAccount($user, 10000);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'Bank to Airtel Money Transfer of KES 250.00 to 254731609277 successfully processed. Transaction Ref ID:REFBA005. Airtel Money Ref ID:REFBA006.',
        ])->assertStatus(404)
            ->assertJson(['error' => 'Bank or Airtel Money account not found']);

        expect(Transfer::withoutGlobalScopes()->where('user_id', $user->id)->count())->toBe(0);
    });
});

// ── Airtel legs ───────────────────────────────────────────────────────────────

describe('airtel legs', function () {

    it('does not double-count when the Airtel receipt arrives after the bank SMS', function () {
        $user   = makeWebhookUser();
        $bank   = makeBankAccount($user, 10000);
        $airtel = makeAirtelAccount($user, 500);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'Bank to Airtel Money Transfer of KES 250.00 to 254731609277 successfully processed. Transaction Ref ID:REFBA010. Airtel Money Ref ID:REFBA011.',
        ])->assertStatus(201);

        // Assumes Airtel's "Airtel Ref" equals the bank SMS's "Airtel Money Ref ID"
        webhookPost([
            'user_id' => $user->id,
            'sms'     => "You've received KES 250.00 from SILAS SEJE. Airtel Ref: REFBA011",
        ])->assertJson(['status' => 'duplicate']);

        expect((float) $bank->fresh()->current_balance)->toBe(9750.0);
        expect((float) $airtel->fresh()->current_balance)->toBe(750.0);
        expect(Transfer::withoutGlobalScopes()->count())->toBe(1);
    });

    it('ignores the Airtel pochi leg of a self transfer to own mpesa', function () {
        $user = makeWebhookUser();
        makeAirtelAccount($user, 1000);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'AB12CD. Ksh 50 sent to SILAS SEJE 254708745191 on 01/05/26 at 11:25 AM. Fee: Ksh 4. Bal: Ksh 946.0. MPESA ID: UE188204V1',
        ])->assertStatus(200)->assertJson(['status' => 'ignored']);

        expect(Transaction::withoutGlobalScopes()->count())->toBe(0);
    });

    it('records an Airtel pochi payment to someone else, keyed on the MPESA ID', function () {
        $user   = makeWebhookUser();
        $airtel = makeAirtelAccount($user, 1000);

        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'AB12CE. Ksh 50 sent to JANE DOE 254722123456 on 01/05/26 at 11:25 AM. Fee: Ksh 4. Bal: Ksh 946.0. MPESA ID: UE188204V9',
        ])->assertStatus(201)->assertJson([
            'bank'      => 'airtel',
            'subtype'   => 'pochi',
            'reference' => 'UE188204V9',
            'fee'       => 4.0,
        ]);

        expect((float) $airtel->fresh()->current_balance)->toBe(946.0);
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
        expect((float) $bank->fresh()->current_balance)->toEqualWithDelta(39962.05, 0.001);
        // Cash: 1000 + 10000 — fee stays on bank
        expect((float) $cash->fresh()->current_balance)->toBe(11000.0);
    });

    // EXPECTED TO FAIL until MpesaSmsParser derives the ATM reference from the SMS.
    // Currently: 'ATM-' . date('YmdHis') → a re-delivered SMS gets a new reference,
    // so the duplicate guard never fires (second transfer + second KES 37.95 fee).
    // Fix: 'reference' => 'ATM-' . substr(sha1($m[1] . $m[2] . $m[3] . trim($m[4])), 0, 12)
    it('detects a re-delivered ATM SMS', function () {
        $user = makeWebhookUser();
        makeBankAccount($user, 50000);
        makeCashAccount($user, 0);

        $payload = [
            'user_id' => $user->id,
            'sms'     => 'Dear SILAS, you withdrew KES 30,000.00 on 2026-04-25 09:59:05 at I&M BANK KENYATTA KE 2 using I&M 5477********9433.',
        ];

        webhookPost($payload)->assertStatus(201);
        webhookPost($payload)->assertStatus(200)->assertJson(['status' => 'duplicate']);

        expect(Transfer::withoutGlobalScopes()->count())->toBe(1);
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

        // Timestamp moved outside the 5–7 AM "Fare" commute window (see
        // TransactionRecorder::isFareTime()) — that window force-overrides
        // category to 'Fare' for any expense regardless of recipient, which
        // would otherwise mask what this test is actually checking.
        webhookPost([
            'user_id' => $user->id,
            'sms'     => 'UDU882J1IZ Confirmed. KES50.00 sent to MAGDALENE WAMBUI on 30/4/26 at 9:34 AM. New M-PESA balance is KES19,425.30. Transaction cost, KES0.00.',
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
