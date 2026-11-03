<?php
// tests/Feature/ReferrerLedgerServiceTest.php
//
// NOTE ON ASSUMPTIONS (I could not see these models/migrations):
//   - Referrer: columns user_id, name, contact, is_active; relations floatAccount()
//     (the account with type 'referrer_float') and loans() (hasMany LoanGiven by referrer_id).
//   - LoanGiven: user_id, referrer_id, borrower_name, disbursement_transaction_id.
//   - LoanGivenPayment: user_id, loan_given_id, transaction_id, payment_date, referrer_commission,
//     referrer_commission_kept, referrer_payout_id.
//   - ReferrerPayout: referrer_id, account_id, paid_date (and transaction_id, nullable).
//   - ReferrerFloatReconciliation: user_id, loan_given_id, transfer_id, amount, component.
// If any of those tables has other NOT NULL columns, add them in the ledger* helpers below.
//
// All helpers are prefixed "ledger" so they don't collide with the global helper
// functions in MpesaSmsWebhookTest.php (Pest loads every test file into one namespace).

use App\Models\Account;
use App\Models\Category;
use App\Models\LoanGiven;
use App\Models\LoanGivenPayment;
use App\Models\Referrer;
use App\Models\ReferrerFloatReconciliation;
use App\Models\ReferrerPayout;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use App\Services\ReferrerLedgerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function ledgerService(): ReferrerLedgerService
{
    return app(ReferrerLedgerService::class);
}

function ledgerUser(): User
{
    return User::factory()->create();
}

function ledgerAccount(User $user, string $type, string $name, float $balance = 0): Account
{
    return Account::factory()->create([
        'user_id'         => $user->id,
        'type'            => $type,
        'name'            => $name,
        'initial_balance' => $balance,
        'current_balance' => $balance,
        'is_active'       => true,
    ]);
}

function ledgerReferrer(User $user, string $name = 'Phides'): Referrer
{
    return Referrer::forceCreate([
        'user_id'   => $user->id,
        'name'      => $name,
        'contact'   => '0713123724',
        'is_active' => true,
    ]);
}

function ledgerFloat(User $user, Referrer $referrer, float $balance = 0): Account
{
    return Account::factory()->create([
        'user_id'         => $user->id,
        'type'            => 'referrer_float',
        'name'            => $referrer->name . ' Float',
        'referrer_id'     => $referrer->id,
        'initial_balance' => $balance,
        'current_balance' => $balance,
        'is_active'       => true,
    ]);
}

function ledgerCategory(User $user, string $name, string $type): Category
{
    return Category::where('user_id', $user->id)->where('name', $name)->first()
        ?? Category::factory()->create(['user_id' => $user->id, 'name' => $name, 'type' => $type]);
}

function ledgerTx(Account $account, Category $category, float $amount, string $date, array $extra = []): Transaction
{
    $createdAt = Carbon::parse($date . ' 09:00:00');

    return Transaction::forceCreate(array_merge([
        'user_id'     => $account->user_id,
        'account_id'  => $account->id,
        'category_id' => $category->id,
        'amount'      => $amount,
        'date'        => $date,
        'description' => 'tx',
        'created_at'  => $createdAt,
        'updated_at'  => $createdAt,
    ], $extra));
}

function ledgerTransfer(User $user, Account $from, Account $to, float $amount, string $date, ?string $valueDate = null): Transfer
{
    return Transfer::forceCreate([
        'user_id'         => $user->id,
        'from_account_id' => $from->id,
        'to_account_id'   => $to->id,
        'amount'          => $amount,
        'date'            => $date . ' 12:00:00',
        'value_date'      => $valueDate,
        'description'     => 'ledger test transfer',
        'created_at'      => Carbon::parse($date . ' 12:00:00'),
        'updated_at'      => Carbon::parse($date . ' 12:00:00'),
    ]);
}

/**
 * Adds a placeholder value for every NOT NULL column (without a default) that
 * $attrs doesn't already set, by reading the table's schema. This keeps the
 * fixtures working when a table has columns the tests don't care about
 * (principal_amount, rates, dates, ...). Columns ending in _id are skipped,
 * because a made-up foreign key would violate its constraint: set those explicitly.
 */
function ledgerFillRequired(string $table, array $attrs): array
{
    $missingIds = [];

    foreach (DB::select("SHOW COLUMNS FROM `{$table}`") as $col) {
        $name  = $col->Field;
        $extra = strtolower((string) $col->Extra);

        if (
            array_key_exists($name, $attrs)
            || $col->Null === 'YES'
            || $col->Default !== null
            || str_contains($extra, 'auto_increment')
            || str_contains($extra, 'generated')
            || in_array($name, ['created_at', 'updated_at', 'deleted_at'], true)
        ) {
            continue;
        }

        if (str_ends_with($name, '_id')) {
            $missingIds[] = $name;
            continue;
        }

        $type = strtolower($col->Type);

        $attrs[$name] = match (true) {
            str_starts_with($type, 'enum(')                              => preg_match("/enum\('([^']*)'/", $col->Type, $m) ? $m[1] : '',
            str_starts_with($type, 'json')                               => '[]',
            str_starts_with($type, 'datetime'), str_starts_with($type, 'timestamp') => '2026-07-01 00:00:00',
            $type === 'date'                                             => '2026-07-01',
            str_contains($type, 'int'), str_contains($type, 'decimal'),
            str_contains($type, 'float'), str_contains($type, 'double')  => 0,
            default                                                      => 'test',
        };
    }

    if ($missingIds) {
        throw new RuntimeException(
            "ledgerFillRequired: `{$table}` has NOT NULL foreign keys the fixture doesn't set: "
            . implode(', ', $missingIds) . '. Set them explicitly in the helper.'
        );
    }

    return $attrs;
}

function ledgerLoan(User $user, Referrer $referrer, string $borrower, ?int $disbursementTxId = null, ?Account $fundedFrom = null): LoanGiven
{
    // loans_given.account_id is NOT NULL: the account the loan was funded from.
    $fundedFrom ??= ledgerAccount($user, 'bank', 'Loan Funding');

    return LoanGiven::forceCreate(ledgerFillRequired('loans_given', [
        'user_id'                     => $user->id,
        'account_id'                  => $fundedFrom->id,
        'referrer_id'                 => $referrer->id,
        'borrower_name'               => $borrower,
        'principal_amount'            => 1000,
        'disbursement_transaction_id' => $disbursementTxId,
    ]));
}

function ledgerPayment(LoanGiven $loan, array $attrs = []): LoanGivenPayment
{
    // loan_given_payments.user_id and account_id are NOT NULL: the loan's owner and
    // the account the payment landed in (defaults to the loan's funding account).
    return LoanGivenPayment::forceCreate(ledgerFillRequired(
        (new LoanGivenPayment())->getTable(),
        array_merge([
            'user_id'       => $loan->user_id,
            'account_id'    => $loan->account_id,
            'loan_given_id' => $loan->id,
            'payment_date'  => '2026-07-01',
        ], $attrs)
    ));
}

function ledgerPayout(Referrer $referrer, string $paidDate): ReferrerPayout
{
    $table = (new ReferrerPayout())->getTable();

    // referrer_payouts.account_id is NOT NULL: the account the payout was paid from.
    $paidFrom = Account::factory()->create([
        'user_id'         => $referrer->user_id,
        'type'            => 'bank',
        'name'            => 'Payout Source',
        'initial_balance' => 0,
        'current_balance' => 0,
        'is_active'       => true,
    ]);

    return ReferrerPayout::forceCreate(ledgerFillRequired($table, array_merge(
        [
            'referrer_id' => $referrer->id,
            'account_id'  => $paidFrom->id,
            'paid_date'   => $paidDate,
        ],
        Schema::hasColumn($table, 'user_id') ? ['user_id' => $referrer->user_id] : []
    )));
}

function ledgerReconcile(User $user, LoanGiven $loan, Transfer $transfer, float $amount, string $component): ReferrerFloatReconciliation
{
    return ReferrerFloatReconciliation::forceCreate(ledgerFillRequired(
        (new ReferrerFloatReconciliation())->getTable(),
        [
            'user_id'       => $user->id,
            'loan_given_id' => $loan->id,
            'transfer_id'   => $transfer->id,
            'amount'        => $amount,
            'component'     => $component,
        ]
    ));
}

/**
 * Same-day scenario with a loan disbursement (09:00), an unrelated credit (09:30)
 * and the loan's fee (10:00). The fee must sit directly beneath its loan even
 * though the credit was created in between. $viaRelatedFee links the fee through
 * the disbursement's related_fee_transaction_id instead of fee_for_transaction_id.
 *
 * @return array<int,string> row titles in ledger order
 */
function ledgerFeeBeneathLoanTitles(bool $viaRelatedFee): array
{
    $user     = ledgerUser();
    $referrer = ledgerReferrer($user);
    $float    = ledgerFloat($user, $referrer, 10000);

    $loanCat   = ledgerCategory($user, 'Friend Loan Given', 'expense');
    $incomeCat = ledgerCategory($user, 'Float Top-up', 'income');
    $feeCat    = ledgerCategory($user, 'Transaction Fees', 'expense');

    $disb = ledgerTx($float, $loanCat, 1000, '2026-07-01', [
        'description' => 'Loan out',
        'created_at'  => Carbon::parse('2026-07-01 09:00:00'),
    ]);

    ledgerTx($float, $incomeCat, 500, '2026-07-01', [
        'description' => 'Capital in',
        'created_at'  => Carbon::parse('2026-07-01 09:30:00'),
    ]);

    $fee = ledgerTx($float, $feeCat, 20, '2026-07-01', array_merge([
        'description'        => 'Fee',
        'is_transaction_fee' => true,
        'created_at'         => Carbon::parse('2026-07-01 10:00:00'),
    ], $viaRelatedFee ? [] : ['fee_for_transaction_id' => $disb->id]));

    if ($viaRelatedFee) {
        $disb->forceFill(['related_fee_transaction_id' => $fee->id])->save();
    }

    return ledgerService()->floatLedger($referrer->fresh())['rows']->pluck('title')->all();
}

// ── floatLedger: basics ───────────────────────────────────────────────────────

describe('floatLedger basics', function () {

    it('returns a null account and no rows when the referrer has no float account', function () {
        $user     = ledgerUser();
        $referrer = ledgerReferrer($user);

        $result = ledgerService()->floatLedger($referrer->fresh());

        expect($result['account'])->toBeNull();
        expect($result['rows'])->toBeEmpty();
    });

    it('builds a running balance from the opening balance with credits and debits', function () {
        $user     = ledgerUser();
        $referrer = ledgerReferrer($user);
        $float    = ledgerFloat($user, $referrer, 1000);

        ledgerTx($float, ledgerCategory($user, 'Float Top-up', 'income'), 500, '2026-07-01', ['description' => 'Capital in']);
        ledgerTx($float, ledgerCategory($user, 'Other Expenses', 'expense'), 300, '2026-07-02', ['description' => 'Spent']);

        $float->updateBalance();

        $l = ledgerService()->floatLedger($referrer->fresh());

        expect($l['rows']->pluck('title')->all())->toBe(['Capital in', 'Spent']);
        expect($l['rows']->pluck('balance')->all())->toBe([1500.0, 1200.0]);
        expect($l['opening'])->toBe(1000.0);
        expect($l['total_credits'])->toEqualWithDelta(500, 0.001);
        expect($l['total_debits'])->toEqualWithDelta(300, 0.001);
        expect($l['closing'])->toBe(1200.0);
        // ledger and stored balance agree
        expect($l['difference'])->toEqualWithDelta(0, 0.001);
    });

    it('reports a non-zero difference when the stored balance disagrees with the ledger', function () {
        $user     = ledgerUser();
        $referrer = ledgerReferrer($user);
        $float    = ledgerFloat($user, $referrer, 1000);

        ledgerTx($float, ledgerCategory($user, 'Float Top-up', 'income'), 100, '2026-07-01');

        // Creating a transaction resyncs current_balance (model event), so force a
        // stale stored balance with a raw query, which fires no model events.
        Account::withoutGlobalScopes()->whereKey($float->id)->toBase()->update(['current_balance' => 1000]);

        $l = ledgerService()->floatLedger($referrer->fresh());

        expect($l['closing'])->toBe(1100.0);
        expect($l['difference'])->toEqualWithDelta(-100, 0.001);
    });

    it('groups rows by month with opening and closing balances', function () {
        $user     = ledgerUser();
        $referrer = ledgerReferrer($user);
        $float    = ledgerFloat($user, $referrer, 1000);

        ledgerTx($float, ledgerCategory($user, 'Float Top-up', 'income'), 200, '2026-07-15');
        ledgerTx($float, ledgerCategory($user, 'Other Expenses', 'expense'), 50, '2026-08-10');

        $months = ledgerService()->floatLedger($referrer->fresh())['months'];

        expect($months)->toHaveCount(2);

        expect($months[0]['label'])->toBe('July 2026');
        expect($months[0]['opening'])->toBe(1000.0);
        expect($months[0]['closing'])->toBe(1200.0);
        expect($months[0]['credits'])->toEqualWithDelta(200, 0.001);

        expect($months[1]['label'])->toBe('August 2026');
        expect($months[1]['opening'])->toBe(1200.0);
        expect($months[1]['closing'])->toBe(1150.0);
        expect($months[1]['debits'])->toEqualWithDelta(50, 0.001);
    });
});

// ── floatLedger: signed effect rules (mirrors Account::updateBalance) ─────────

describe('floatLedger signed effects', function () {

    it('does not count income with a future value date and labels when it clears', function () {
        $user     = ledgerUser();
        $referrer = ledgerReferrer($user);
        $float    = ledgerFloat($user, $referrer, 0);

        $clears = now()->addDays(10);

        ledgerTx($float, ledgerCategory($user, 'Float Top-up', 'income'), 400, '2026-07-01', [
            'description' => 'Top-up',
            'value_date'  => $clears->toDateString(),
        ]);

        $l = ledgerService()->floatLedger($referrer->fresh());

        expect($l['rows'][0]['credit'])->toEqualWithDelta(0, 0.001);
        expect($l['rows'][0]['title'])->toBe('Top-up (clears ' . $clears->format('M j') . ')');
        expect($l['closing'])->toBe(0.0);
    });

    it('always counts Interest income even with a future value date', function () {
        $user     = ledgerUser();
        $referrer = ledgerReferrer($user);
        $float    = ledgerFloat($user, $referrer, 0);

        ledgerTx($float, ledgerCategory($user, 'Interest', 'income'), 100, '2026-07-01', [
            'description' => 'Interest posted',
            'value_date'  => now()->addDays(10)->toDateString(),
        ]);

        $l = ledgerService()->floatLedger($referrer->fresh());

        expect($l['rows'][0]['credit'])->toEqualWithDelta(100, 0.001);
        expect($l['closing'])->toBe(100.0);
    });

    it('treats Balance Adjustment as an already-signed amount', function () {
        $user     = ledgerUser();
        $referrer = ledgerReferrer($user);
        $float    = ledgerFloat($user, $referrer, 1000);

        $cat = ledgerCategory($user, 'Balance Adjustment', 'income');
        ledgerTx($float, $cat, -75, '2026-07-01', ['description' => 'Correction']);

        $l = ledgerService()->floatLedger($referrer->fresh());

        expect($l['rows'][0]['debit'])->toEqualWithDelta(75, 0.001);
        expect($l['closing'])->toBe(925.0);
    });

    it('labels fees as Transaction fee with kind "fee"', function () {
        $user     = ledgerUser();
        $referrer = ledgerReferrer($user);
        $float    = ledgerFloat($user, $referrer, 1000);

        ledgerTx($float, ledgerCategory($user, 'Transaction Fees', 'expense'), 27, '2026-07-01', [
            'is_transaction_fee' => true,
        ]);

        $row = ledgerService()->floatLedger($referrer->fresh())['rows'][0];

        expect($row['title'])->toBe('Transaction fee');
        expect($row['kind'])->toBe('fee');
        expect($row['debit'])->toEqualWithDelta(27, 0.001);
        expect($row['component'])->toBeNull();
    });
});

// ── floatLedger: ordering ─────────────────────────────────────────────────────

describe('floatLedger ordering', function () {

    it('moves a same-day credit ahead of a debit so the balance never goes negative', function () {
        $user     = ledgerUser();
        $referrer = ledgerReferrer($user);
        $float    = ledgerFloat($user, $referrer, 0);

        // Debit was created first (09:00), the credit that funds it later (10:00)
        ledgerTx($float, ledgerCategory($user, 'Other Expenses', 'expense'), 300, '2026-07-01', [
            'description' => 'Spent',
            'created_at'  => Carbon::parse('2026-07-01 09:00:00'),
        ]);
        ledgerTx($float, ledgerCategory($user, 'Float Top-up', 'income'), 500, '2026-07-01', [
            'description' => 'Capital in',
            'created_at'  => Carbon::parse('2026-07-01 10:00:00'),
        ]);

        $l = ledgerService()->floatLedger($referrer->fresh());

        expect($l['rows']->pluck('title')->all())->toBe(['Capital in', 'Spent']);
        expect($l['rows']->pluck('balance')->all())->toBe([500.0, 200.0]);
    });

    it('never pulls a credit across days to cover a debit', function () {
        $user     = ledgerUser();
        $referrer = ledgerReferrer($user);
        $float    = ledgerFloat($user, $referrer, 0);

        ledgerTx($float, ledgerCategory($user, 'Other Expenses', 'expense'), 300, '2026-07-01', ['description' => 'Spent']);
        ledgerTx($float, ledgerCategory($user, 'Float Top-up', 'income'), 500, '2026-07-02', ['description' => 'Capital in']);

        $l = ledgerService()->floatLedger($referrer->fresh());

        expect($l['rows']->pluck('title')->all())->toBe(['Spent', 'Capital in']);
        expect($l['rows']->pluck('balance')->all())->toBe([-300.0, 200.0]);
    });

    it('keeps same-day rows in creation order when no overdraft would occur', function () {
        $user     = ledgerUser();
        $referrer = ledgerReferrer($user);
        $float    = ledgerFloat($user, $referrer, 10000);

        ledgerTx($float, ledgerCategory($user, 'Other Expenses', 'expense'), 100, '2026-07-01', [
            'description' => 'First',
            'created_at'  => Carbon::parse('2026-07-01 09:00:00'),
        ]);
        ledgerTx($float, ledgerCategory($user, 'Float Top-up', 'income'), 50, '2026-07-01', [
            'description' => 'Second',
            'created_at'  => Carbon::parse('2026-07-01 11:00:00'),
        ]);

        $titles = ledgerService()->floatLedger($referrer->fresh())['rows']->pluck('title')->all();

        expect($titles)->toBe(['First', 'Second']);
    });

    it('keeps a loan fee directly beneath its loan (fee_for_transaction_id)', function () {
        expect(ledgerFeeBeneathLoanTitles(false))
            ->toBe(['Loan given: borrower', 'Transaction fee', 'Capital in']);
    });

    it('keeps a loan fee directly beneath its loan (related_fee_transaction_id only)', function () {
        expect(ledgerFeeBeneathLoanTitles(true))
            ->toBe(['Loan given: borrower', 'Transaction fee', 'Capital in']);
    });
});

// ── floatLedger: transfers ────────────────────────────────────────────────────

describe('floatLedger transfers', function () {

    it('shows a transfer in as a credit with no component', function () {
        $user     = ledgerUser();
        $referrer = ledgerReferrer($user);
        $float    = ledgerFloat($user, $referrer, 0);
        $mpesa    = ledgerAccount($user, 'mpesa', 'Mpesa', 5000);

        ledgerTransfer($user, $mpesa, $float, 1000, '2026-07-01');

        $row = ledgerService()->floatLedger($referrer->fresh())['rows'][0];

        expect($row['title'])->toBe('Transfer in: Mpesa');
        expect($row['credit'])->toEqualWithDelta(1000, 0.001);
        expect($row['kind'])->toBe('transfer_in');
        expect($row['component'])->toBeNull();
    });

    it('does not count a transfer in until its value date and labels when it clears', function () {
        $user     = ledgerUser();
        $referrer = ledgerReferrer($user);
        $float    = ledgerFloat($user, $referrer, 0);
        $mpesa    = ledgerAccount($user, 'mpesa', 'Mpesa', 5000);

        $clears = now()->addDays(5);
        ledgerTransfer($user, $mpesa, $float, 1000, '2026-07-01', $clears->toDateString());

        $l = ledgerService()->floatLedger($referrer->fresh());

        expect($l['rows'][0]['title'])->toBe('Transfer in: Mpesa (clears ' . $clears->format('M j') . ')');
        expect($l['rows'][0]['credit'])->toEqualWithDelta(0, 0.001);
        expect($l['closing'])->toBe(0.0);
    });

    it('treats an unreconciled outgoing transfer as principal going back to you', function () {
        $user     = ledgerUser();
        $referrer = ledgerReferrer($user);
        $float    = ledgerFloat($user, $referrer, 5000);
        $mpesa    = ledgerAccount($user, 'mpesa', 'Mpesa', 0);

        ledgerTransfer($user, $float, $mpesa, 500, '2026-07-01');

        $l = ledgerService()->floatLedger($referrer->fresh());

        expect($l['rows'])->toHaveCount(1);
        expect($l['rows'][0]['title'])->toBe('Remitted to Mpesa');
        expect($l['rows'][0]['debit'])->toEqualWithDelta(500, 0.001);
        expect($l['rows'][0]['component'])->toBe('principal');
        expect($l['rows'][0]['kind'])->toBe('remitted');
        expect($l['closing'])->toBe(4500.0);
    });

    it('splits an outgoing transfer into principal, interest and leftover rows, with its fee last', function () {
        $user     = ledgerUser();
        $referrer = ledgerReferrer($user);
        $float    = ledgerFloat($user, $referrer, 10000);
        $mpesa    = ledgerAccount($user, 'mpesa', 'Mpesa', 0);
        $loan     = ledgerLoan($user, $referrer, 'Jane');

        $transfer = ledgerTransfer($user, $float, $mpesa, 700, '2026-07-10');
        ledgerReconcile($user, $loan, $transfer, 200, 'principal');
        ledgerReconcile($user, $loan, $transfer, 300, 'interest');

        // 700 - (200 + 300) = 200 left over, which counts as principal
        ledgerTx($float, ledgerCategory($user, 'Transaction Fees', 'expense'), 10, '2026-07-10', [
            'is_transaction_fee' => true,
            'transfer_id'        => $transfer->id,
            'created_at'         => Carbon::parse('2026-07-10 12:30:00'),
        ]);

        $l = ledgerService()->floatLedger($referrer->fresh());

        expect($l['rows']->pluck('title')->all())->toBe([
            'Remitted to Mpesa (principal)',
            'Remitted to Mpesa (interest)',
            'Remitted to Mpesa',
            'Transaction fee',
        ]);
        expect($l['rows']->pluck('balance')->all())->toBe([9800.0, 9500.0, 9300.0, 9290.0]);

        // focused ledgers
        expect($l['by_component']['principal']['rows'])->toHaveCount(2);
        expect($l['by_component']['principal']['debits'])->toEqualWithDelta(400, 0.001);
        expect($l['by_component']['principal']['closing'])->toBe(-400.0);

        expect($l['by_component']['interest']['rows'])->toHaveCount(1);
        expect($l['by_component']['interest']['debits'])->toEqualWithDelta(300, 0.001);
        expect($l['by_component']['interest']['closing'])->toBe(-300.0);
    });
});

// ── floatLedger: principal / interest focused ledgers ─────────────────────────

describe('floatLedger focused ledgers', function () {

    it('files recoveries under principal, interest and commission under interest', function () {
        $user     = ledgerUser();
        $referrer = ledgerReferrer($user, 'Phides');
        $float    = ledgerFloat($user, $referrer, 0);
        $loan     = ledgerLoan($user, $referrer, 'Jane');

        $recovery = ledgerTx($float, ledgerCategory($user, 'Loan Recovery', 'income'), 1000, '2026-07-01');
        $payment  = ledgerPayment($loan, ['transaction_id' => $recovery->id]);

        ledgerTx($float, ledgerCategory($user, 'Loan Interest', 'income'), 100, '2026-07-01', [
            'reference_id' => $payment->id,
        ]);
        ledgerTx($float, ledgerCategory($user, 'Referrer Commission', 'expense'), 30, '2026-07-01');

        $l = ledgerService()->floatLedger($referrer->fresh());

        expect($l['rows']->pluck('title')->all())->toBe([
            'Principal: Jane',
            'Interest: Jane',
            'Commission paid: Phides',
        ]);
        expect($l['rows']->pluck('kind')->all())->toBe(['principal', 'interest', 'commission']);

        $principal = $l['by_component']['principal'];
        expect($principal['label'])->toBe('Principal');
        expect($principal['rows'])->toHaveCount(1);
        expect($principal['credits'])->toEqualWithDelta(1000, 0.001);
        expect($principal['closing'])->toBe(1000.0);

        $interest = $l['by_component']['interest'];
        expect($interest['label'])->toBe('Interest');
        expect($interest['rows'])->toHaveCount(2);
        expect($interest['credits'])->toEqualWithDelta(100, 0.001);
        expect($interest['debits'])->toEqualWithDelta(30, 0.001);
        expect($interest['closing'])->toBe(70.0);
    });

    it('keeps a Loan Recovery without a payment record out of the principal ledger', function () {
        $user     = ledgerUser();
        $referrer = ledgerReferrer($user);
        $float    = ledgerFloat($user, $referrer, 0);

        ledgerTx($float, ledgerCategory($user, 'Loan Recovery', 'income'), 500, '2026-07-01');

        $l = ledgerService()->floatLedger($referrer->fresh());

        expect($l['rows'])->toHaveCount(1);
        expect($l['rows'][0]['title'])->toBe('Principal: borrower');
        expect($l['rows'][0]['component'])->toBeNull();
        expect($l['by_component']['principal']['rows'])->toBeEmpty();
    });

    it('keeps capital, loans given and fees out of both focused ledgers', function () {
        $user     = ledgerUser();
        $referrer = ledgerReferrer($user);
        $float    = ledgerFloat($user, $referrer, 5000);

        ledgerTx($float, ledgerCategory($user, 'Float Top-up', 'income'), 100, '2026-07-01');
        ledgerTx($float, ledgerCategory($user, 'Friend Loan Given', 'expense'), 200, '2026-07-01');
        ledgerTx($float, ledgerCategory($user, 'Transaction Fees', 'expense'), 5, '2026-07-01', ['is_transaction_fee' => true]);

        $l = ledgerService()->floatLedger($referrer->fresh());

        expect($l['rows'])->toHaveCount(3);
        expect($l['by_component']['principal']['rows'])->toBeEmpty();
        expect($l['by_component']['interest']['rows'])->toBeEmpty();
    });
});

// ── commissionLedger ──────────────────────────────────────────────────────────

describe('commissionLedger', function () {

    it('is empty for a referrer with no loans', function () {
        $user     = ledgerUser();
        $referrer = ledgerReferrer($user);

        $l = ledgerService()->commissionLedger($referrer->fresh());

        expect($l['rows'])->toBeEmpty();
        expect($l['total_earned'])->toEqualWithDelta(0, 0.001);
        expect($l['total_settled'])->toEqualWithDelta(0, 0.001);
        expect($l['still_owed'])->toEqualWithDelta(0, 0.001);
    });

    it('tracks commission earned, kept, paid and still owed', function () {
        $user     = ledgerUser();
        $referrer = ledgerReferrer($user);
        $other    = ledgerReferrer($user, 'Someone Else');

        $jane = ledgerLoan($user, $referrer, 'Jane');
        $joe  = ledgerLoan($user, $referrer, 'Joe');
        $bob  = ledgerLoan($user, $other, 'Bob');

        $payout = ledgerPayout($referrer, '2026-07-10');

        // earned 100 and kept by you the same day
        ledgerPayment($jane, [
            'payment_date'             => '2026-07-01',
            'referrer_commission'      => 100,
            'referrer_commission_kept' => true,
        ]);
        // earned 200, paid out on 10 July
        ledgerPayment($joe, [
            'payment_date'        => '2026-07-05',
            'referrer_commission' => 200,
            'referrer_payout_id'  => $payout->id,
        ]);
        // earned 50, not settled yet
        ledgerPayment($jane, [
            'payment_date'        => '2026-07-20',
            'referrer_commission' => 50,
        ]);
        // ignored: no commission
        ledgerPayment($jane, ['payment_date' => '2026-07-21', 'referrer_commission' => 0]);
        // ignored: belongs to another referrer's loan
        ledgerPayment($bob, ['payment_date' => '2026-07-22', 'referrer_commission' => 999]);

        $l = ledgerService()->commissionLedger($referrer->fresh());

        expect($l['rows']->pluck('title')->all())->toBe([
            'Earned: Jane',
            'Kept: Jane',
            'Earned: Joe',
            'Paid: Joe',
            'Earned: Jane',
        ]);
        expect($l['rows']->pluck('balance')->all())->toBe([100.0, 0.0, 200.0, 0.0, 50.0]);
        expect($l['total_earned'])->toEqualWithDelta(350, 0.001);
        expect($l['total_settled'])->toEqualWithDelta(300, 0.001);
        expect($l['still_owed'])->toBe(50.0);
    });

    it('dates a "Paid" line on the payout date, not the payment date', function () {
        $user     = ledgerUser();
        $referrer = ledgerReferrer($user);
        $loan     = ledgerLoan($user, $referrer, 'Jane');

        $payout = ledgerPayout($referrer, '2026-08-15');

        ledgerPayment($loan, [
            'payment_date'        => '2026-07-01',
            'referrer_commission' => 80,
            'referrer_payout_id'  => $payout->id,
        ]);

        $rows = ledgerService()->commissionLedger($referrer->fresh())['rows'];

        expect($rows[0]['date']->format('Y-m-d'))->toBe('2026-07-01');
        expect($rows[1]['title'])->toBe('Paid: Jane');
        expect($rows[1]['date']->format('Y-m-d'))->toBe('2026-08-15');
    });
});

// ── allMovements ──────────────────────────────────────────────────────────────

describe('allMovements', function () {

    it('is empty for a referrer with no float account and no loans', function () {
        $user     = ledgerUser();
        $referrer = ledgerReferrer($user);

        $r = ledgerService()->allMovements($referrer->fresh());

        expect($r['count'])->toBe(0);
        expect($r['rows'])->toBeEmpty();
    });

    it('lists transfers in and out of the float with account labels and allocation notes', function () {
        $user     = ledgerUser();
        $referrer = ledgerReferrer($user);
        $float    = ledgerFloat($user, $referrer, 0);
        $mpesa    = ledgerAccount($user, 'mpesa', 'Mpesa', 5000);

        ledgerTransfer($user, $mpesa, $float, 1000, '2026-07-01');
        ledgerTransfer($user, $float, $mpesa, 400, '2026-07-10');

        $r = ledgerService()->allMovements($referrer->fresh());

        expect($r['count'])->toBe(2);

        $in = $r['rows'][0];
        expect($in['title'])->toBe('Transfer in from Mpesa');
        expect($in['account'])->toBe('Mpesa → Phides Float');
        expect($in['in'])->toEqualWithDelta(1000, 0.001);
        expect($in['kind'])->toBe('transfer_in');
        expect($in['in_float'])->toBeTrue();

        $out = $r['rows'][1];
        expect($out['title'])->toBe('Remitted to Mpesa');
        expect($out['account'])->toBe('Phides Float → Mpesa');
        expect($out['out'])->toEqualWithDelta(400, 0.001);
        expect($out['kind'])->toBe('remitted');
        expect($out['note'])->toBe('not allocated 400');

        expect($r['months'])->toHaveCount(1);
        expect($r['months'][0]['label'])->toBe('July 2026');
    });

    it('describes how an outgoing transfer was allocated', function () {
        $user     = ledgerUser();
        $referrer = ledgerReferrer($user);
        $float    = ledgerFloat($user, $referrer, 0);
        $mpesa    = ledgerAccount($user, 'mpesa', 'Mpesa', 0);
        $loan     = ledgerLoan($user, $referrer, 'Jane');

        $transfer = ledgerTransfer($user, $float, $mpesa, 700, '2026-07-10');
        ledgerReconcile($user, $loan, $transfer, 200, 'principal');
        ledgerReconcile($user, $loan, $transfer, 300, 'interest');

        $row = ledgerService()->allMovements($referrer->fresh())['rows'][0];

        expect($row['note'])->toBe('principal 200 · interest 300 · not allocated 200');
    });

    it('includes a referred loan paid from another account, with its fee directly beneath', function () {
        $user     = ledgerUser();
        $referrer = ledgerReferrer($user);
        $mpesa    = ledgerAccount($user, 'mpesa', 'Mpesa', 10000);

        $disb = ledgerTx($mpesa, ledgerCategory($user, 'Friend Loan Given', 'expense'), 1000, '2026-07-01', [
            'description' => 'Loan to Jane',
        ]);
        ledgerLoan($user, $referrer, 'Jane', $disb->id, $mpesa);

        ledgerTx($mpesa, ledgerCategory($user, 'Transaction Fees', 'expense'), 20, '2026-07-01', [
            'is_transaction_fee'     => true,
            'fee_for_transaction_id' => $disb->id,
            'created_at'             => Carbon::parse('2026-07-01 10:00:00'),
        ]);

        // Unrelated Mpesa activity must not appear
        ledgerTx($mpesa, ledgerCategory($user, 'Other Expenses', 'expense'), 5, '2026-07-01', ['description' => 'Unrelated']);

        $r = ledgerService()->allMovements($referrer->fresh());

        expect($r['rows']->pluck('title')->all())->toBe(['Loan given: Jane', 'Transaction fee (Jane)']);
        expect($r['rows'][0]['account'])->toBe('Mpesa');
        expect($r['rows'][0]['out'])->toEqualWithDelta(1000, 0.001);
        expect($r['rows'][0]['in_float'])->toBeFalse();
        expect($r['rows'][1]['kind'])->toBe('fee');
    });

    it('flags interest rows that were never linked to a payment, and ignores other borrowers', function () {
        $user     = ledgerUser();
        $referrer = ledgerReferrer($user);
        $mpesa    = ledgerAccount($user, 'mpesa', 'Mpesa', 0);
        $loan     = ledgerLoan($user, $referrer, 'Jane');
        $interest = ledgerCategory($user, 'Loan Interest', 'income');

        // linked: reference_id points at one of her loans' payments
        $payment = ledgerPayment($loan, ['payment_date' => '2026-07-01']);
        ledgerTx($mpesa, $interest, 100, '2026-07-01', [
            'description'  => "Interest earned from Jane's loan",
            'reference_id' => $payment->id,
        ]);

        // unlinked: no reference_id, matched by borrower name in the description
        ledgerTx($mpesa, $interest, 60, '2026-07-02', [
            'description' => "Interest earned from Jane's loan",
        ]);

        // someone else's borrower: must not appear
        ledgerTx($mpesa, $interest, 999, '2026-07-03', [
            'description' => "Interest earned from Bob's loan",
        ]);

        $r = ledgerService()->allMovements($referrer->fresh());

        expect($r['count'])->toBe(2);
        expect($r['rows']->pluck('title')->all())->toBe([
            'Interest: Jane',
            'Interest: Jane (not linked to a payment)',
        ]);
        expect($r['rows']->pluck('unlinked')->all())->toBe([false, true]);
        expect($r['rows']->pluck('in')->all())->toEqualWithDelta([100.0, 60.0], 0.001);
    });
});
