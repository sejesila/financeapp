<?php

namespace Tests\Unit\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use App\Services\StatementDataService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Tests\TestCase;

class StatementTest extends TestCase
{
    use RefreshDatabase;

    protected StatementDataService $service;
    protected User $user;
    protected Account $account;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = new StatementDataService();
        $this->user    = User::factory()->create();
        $this->account = Account::factory()
            ->for($this->user)
            ->create([
                'type'            => 'savings',
                'initial_balance' => 10000,
                'current_balance' => 10000,
                'is_active'       => true,
            ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // CONTROLLER – ACCESS CONTROL
    // ────────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function guests_are_redirected_from_statement()
    {
        $this->get(route('accounts.statement', $this->account))
            ->assertRedirect(route('login'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_403_when_viewing_another_users_statement()
    {
        $other        = User::factory()->create();
        $otherAccount = Account::factory()->for($other)->create(['type' => 'savings']);

        $this->actingAs($this->user)
            ->get(route('accounts.statement', $otherAccount))
            ->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_non_savings_accounts()
    {
        $cashAccount = Account::factory()->for($this->user)->create(['type' => 'cash']);

        $this->actingAs($this->user)
            ->get(route('accounts.statement', $cashAccount))
            ->assertRedirect(route('accounts.show', $cashAccount))
            ->assertSessionHas('error');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_shows_statement_page_for_savings_account_owner()
    {
        $this->actingAs($this->user)
            ->get(route('accounts.statement', $this->account))
            ->assertOk();
    }

    // ────────────────────────────────────────────────────────────────────────
    // CONTROLLER – PDF DOWNLOAD
    // ────────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_a_pdf_download_when_download_flag_is_set()
    {
        // Chrome is not available in the test environment, so mock the PDF facade.
        // Pdf::fake() intercepts the download call without invoking Browsershot.
        Pdf::fake();

        $this->actingAs($this->user)
            ->get(route('accounts.statement', $this->account) . '?download=1')
            ->assertOk();

        Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) {
            return $pdf->viewName === 'accounts.statement'
                && $pdf->isDownload();
        });
    }

    // ────────────────────────────────────────────────────────────────────────
    // buildStatementData – OPENING BALANCE
    // ────────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_uses_initial_balance_as_opening_when_no_prior_transactions_exist()
    {
        $from = Carbon::parse('2026-01-01')->startOfDay();
        $to   = Carbon::parse('2026-01-31')->endOfDay();

        $data = $this->service->buildStatementData($this->account, $from, $to);

        $this->assertEquals(10000.0, $data['openingBalance']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_prior_transactions_in_opening_balance()
    {
        $incomeCategory = $this->createCategory('Salary', 'income');

        // Transaction before the statement period
        Transaction::factory()
            ->for($this->user)
            ->for($this->account)
            ->for($incomeCategory)
            ->create(['amount' => 5000, 'date' => '2025-12-15']);

        $from = Carbon::parse('2026-01-01')->startOfDay();
        $to   = Carbon::parse('2026-01-31')->endOfDay();

        $data = $this->service->buildStatementData($this->account, $from, $to);

        // initial_balance (10000) + prior income (5000)
        $this->assertEquals(15000.0, $data['openingBalance']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_deducts_prior_expenses_from_opening_balance()
    {
        $expenseCategory = $this->createCategory('Food', 'expense');

        Transaction::factory()
            ->for($this->user)
            ->for($this->account)
            ->for($expenseCategory)
            ->create(['amount' => 2000, 'date' => '2025-12-20']);

        $from = Carbon::parse('2026-01-01')->startOfDay();
        $to   = Carbon::parse('2026-01-31')->endOfDay();

        $data = $this->service->buildStatementData($this->account, $from, $to);

        // initial_balance (10000) − prior expense (2000)
        $this->assertEquals(8000.0, $data['openingBalance']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // buildStatementData – ROWS & RUNNING BALANCE
    // ────────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_builds_rows_with_a_running_balance()
    {
        $incomeCategory  = $this->createCategory('Salary', 'income');
        $expenseCategory = $this->createCategory('Food', 'expense');

        Transaction::factory()->for($this->user)->for($this->account)->for($incomeCategory)
            ->create(['amount' => 3000, 'date' => '2026-01-05']);

        Transaction::factory()->for($this->user)->for($this->account)->for($expenseCategory)
            ->create(['amount' => 1000, 'date' => '2026-01-10']);

        $from = Carbon::parse('2026-01-01')->startOfDay();
        $to   = Carbon::parse('2026-01-31')->endOfDay();

        $data = $this->service->buildStatementData($this->account, $from, $to);

        $this->assertCount(2, $data['rows']);
        $this->assertEquals(13000.0, $data['rows'][0]['running_balance']); // 10000 + 3000
        $this->assertEquals(12000.0, $data['rows'][1]['running_balance']); // 13000 − 1000
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_calculates_correct_closing_balance()
    {
        $incomeCategory  = $this->createCategory('Salary', 'income');
        $expenseCategory = $this->createCategory('Food', 'expense');

        Transaction::factory()->for($this->user)->for($this->account)->for($incomeCategory)
            ->create(['amount' => 5000, 'date' => '2026-01-05']);

        Transaction::factory()->for($this->user)->for($this->account)->for($expenseCategory)
            ->create(['amount' => 1500, 'date' => '2026-01-15']);

        $from = Carbon::parse('2026-01-01')->startOfDay();
        $to   = Carbon::parse('2026-01-31')->endOfDay();

        $data = $this->service->buildStatementData($this->account, $from, $to);

        $this->assertEquals(13500.0, $data['closingBalance']); // 10000 + 5000 − 1500
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_sums_total_inflow_and_withdrawal()
    {
        $incomeCategory  = $this->createCategory('Salary', 'income');
        $expenseCategory = $this->createCategory('Food', 'expense');

        Transaction::factory()->for($this->user)->for($this->account)->for($incomeCategory)
            ->create(['amount' => 4000, 'date' => '2026-01-05']);
        Transaction::factory()->for($this->user)->for($this->account)->for($incomeCategory)
            ->create(['amount' => 6000, 'date' => '2026-01-10']);

        Transaction::factory()->for($this->user)->for($this->account)->for($expenseCategory)
            ->create(['amount' => 2000, 'date' => '2026-01-20']);

        $from = Carbon::parse('2026-01-01')->startOfDay();
        $to   = Carbon::parse('2026-01-31')->endOfDay();

        $data = $this->service->buildStatementData($this->account, $from, $to);

        $this->assertEquals(10000.0, $data['totalInflow']);
        $this->assertEquals(2000.0,  $data['totalWithdrawal']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // buildStatementData – INTEREST
    // ────────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_separates_interest_transactions_into_net_interest_column()
    {
        $interestCategory = $this->createCategory('Interest', 'income');

        Transaction::factory()->for($this->user)->for($this->account)->for($interestCategory)
            ->create(['amount' => 250, 'date' => '2026-01-31']);

        $from = Carbon::parse('2026-01-01')->startOfDay();
        $to   = Carbon::parse('2026-01-31')->endOfDay();

        $data = $this->service->buildStatementData($this->account, $from, $to);

        $row = $data['rows'][0];
        $this->assertEquals(250.0, $row['net_interest']);
        $this->assertNull($row['inflow']);
        $this->assertEquals(250.0, $data['totalInterest']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_consolidates_multiple_interest_postings_in_the_same_month()
    {
        $interestCategory = $this->createCategory('Interest', 'income');

        Transaction::factory()->for($this->user)->for($this->account)->for($interestCategory)
            ->create(['amount' => 100, 'date' => '2026-01-15']);
        Transaction::factory()->for($this->user)->for($this->account)->for($interestCategory)
            ->create(['amount' => 150, 'date' => '2026-01-28']);

        $from = Carbon::parse('2026-01-01')->startOfDay();
        $to   = Carbon::parse('2026-01-31')->endOfDay();

        $data = $this->service->buildStatementData($this->account, $from, $to);

        // Two interest rows in Jan 2026 must be merged into one consolidated row
        $interestRows = array_filter($data['rows'], fn ($r) => $r['net_interest'] !== null);
        $this->assertCount(1, $interestRows);

        $consolidated = array_values($interestRows)[0];
        $this->assertEquals(250.0, $consolidated['net_interest']);
        $this->assertStringContainsString('consolidated', $consolidated['narration']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // buildStatementData – PENDING TRANSACTIONS
    // ────────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_marks_income_with_future_value_date_as_pending()
    {
        $incomeCategory = $this->createCategory('Salary', 'income');

        Transaction::factory()->for($this->user)->for($this->account)->for($incomeCategory)
            ->create([
                'amount'     => 8000,
                'date'       => now()->toDateString(),
                'value_date' => now()->addDays(3)->toDateString(),
            ]);

        $from = now()->startOfMonth()->startOfDay();
        $to   = now()->endOfMonth()->endOfDay();

        $data = $this->service->buildStatementData($this->account, $from, $to);

        $row = $data['rows'][0];
        $this->assertTrue($row['pending']);
        $this->assertNull($row['inflow']);         // not yet settled
        $this->assertEquals(8000.0, $row['pending_amount']);
        $this->assertStringContainsString('pending', $row['narration']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_count_pending_income_in_total_inflow()
    {
        $incomeCategory = $this->createCategory('Salary', 'income');

        Transaction::factory()->for($this->user)->for($this->account)->for($incomeCategory)
            ->create([
                'amount'     => 8000,
                'date'       => now()->toDateString(),
                'value_date' => now()->addDays(3)->toDateString(),
            ]);

        $from = now()->startOfMonth()->startOfDay();
        $to   = now()->endOfMonth()->endOfDay();

        $data = $this->service->buildStatementData($this->account, $from, $to);

        $this->assertEquals(0.0, $data['totalInflow']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_treats_interest_as_settled_even_with_future_value_date()
    {
        $interestCategory = $this->createCategory('Interest', 'income');

        Transaction::factory()->for($this->user)->for($this->account)->for($interestCategory)
            ->create([
                'amount'     => 300,
                'date'       => now()->toDateString(),
                'value_date' => now()->addDays(5)->toDateString(),
            ]);

        $from = now()->startOfMonth()->startOfDay();
        $to   = now()->endOfMonth()->endOfDay();

        $data = $this->service->buildStatementData($this->account, $from, $to);

        // Interest is always settled — not pending
        $row = $data['rows'][0];
        $this->assertFalse($row['pending']);
        $this->assertEquals(300.0, $row['net_interest']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // buildStatementData – TRANSFERS
    // ────────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_transfers_in_as_inflow()
    {
        $fromAccount = Account::factory()->for($this->user)->create(['type' => 'cash']);

        Transfer::create([
            'user_id'         => $this->user->id,
            'from_account_id' => $fromAccount->id,
            'to_account_id'   => $this->account->id,
            'amount'          => 2000,
            'date'            => '2026-01-10',
        ]);

        $from = Carbon::parse('2026-01-01')->startOfDay();
        $to   = Carbon::parse('2026-01-31')->endOfDay();

        $data = $this->service->buildStatementData($this->account, $from, $to);

        $row = $data['rows'][0];
        $this->assertEquals(2000.0, $row['inflow']);
        $this->assertEquals('transfer_in', $row['source']);
        $this->assertEquals(2000.0, $data['totalInflow']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_transfers_out_as_withdrawal()
    {
        $toAccount = Account::factory()->for($this->user)->create(['type' => 'cash']);

        Transfer::create([
            'user_id'         => $this->user->id,
            'from_account_id' => $this->account->id,
            'to_account_id'   => $toAccount->id,
            'amount'          => 1500,
            'date'            => '2026-01-15',
        ]);

        $from = Carbon::parse('2026-01-01')->startOfDay();
        $to   = Carbon::parse('2026-01-31')->endOfDay();

        $data = $this->service->buildStatementData($this->account, $from, $to);

        $row = $data['rows'][0];
        $this->assertEquals(1500.0, $row['withdrawal']);
        $this->assertEquals('transfer_out', $row['source']);
        $this->assertEquals(1500.0, $data['totalWithdrawal']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_marks_transfer_in_with_future_value_date_as_pending()
    {
        $fromAccount = Account::factory()->for($this->user)->create(['type' => 'cash']);

        Transfer::create([
            'user_id'         => $this->user->id,
            'from_account_id' => $fromAccount->id,
            'to_account_id'   => $this->account->id,
            'amount'          => 3000,
            'date'            => now()->toDateString(),
            'value_date'      => now()->addDays(2)->toDateString(),
        ]);

        $from = now()->startOfMonth()->startOfDay();
        $to   = now()->endOfMonth()->endOfDay();

        $data = $this->service->buildStatementData($this->account, $from, $to);

        $row = $data['rows'][0];
        $this->assertTrue($row['pending']);
        $this->assertNull($row['inflow']);
        $this->assertEquals(3000.0, $row['pending_amount']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // buildStatementData – ROW ORDERING
    // ────────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_sorts_rows_by_date_ascending()
    {
        $incomeCategory  = $this->createCategory('Salary', 'income');
        $expenseCategory = $this->createCategory('Food', 'expense');

        Transaction::factory()->for($this->user)->for($this->account)->for($expenseCategory)
            ->create(['amount' => 500, 'date' => '2026-01-20', 'description' => 'Late tx']);
        Transaction::factory()->for($this->user)->for($this->account)->for($incomeCategory)
            ->create(['amount' => 1000, 'date' => '2026-01-05', 'description' => 'Early tx']);

        $from = Carbon::parse('2026-01-01')->startOfDay();
        $to   = Carbon::parse('2026-01-31')->endOfDay();

        $data = $this->service->buildStatementData($this->account, $from, $to);

        $this->assertStringContainsString('Early tx', $data['rows'][0]['narration']);
        $this->assertStringContainsString('Late tx',  $data['rows'][1]['narration']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_excludes_transactions_outside_the_date_range()
    {
        $incomeCategory = $this->createCategory('Salary', 'income');

        Transaction::factory()->for($this->user)->for($this->account)->for($incomeCategory)
            ->create(['amount' => 9999, 'date' => '2025-12-31', 'description' => 'Before range']);
        Transaction::factory()->for($this->user)->for($this->account)->for($incomeCategory)
            ->create(['amount' => 9999, 'date' => '2026-02-01', 'description' => 'After range']);

        $from = Carbon::parse('2026-01-01')->startOfDay();
        $to   = Carbon::parse('2026-01-31')->endOfDay();

        $data = $this->service->buildStatementData($this->account, $from, $to);

        $this->assertEmpty($data['rows']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // computeBalanceAt
    // ────────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_computes_balance_at_a_given_point_in_time()
    {
        $incomeCategory  = $this->createCategory('Salary', 'income');
        $expenseCategory = $this->createCategory('Food', 'expense');

        Transaction::factory()->for($this->user)->for($this->account)->for($incomeCategory)
            ->create(['amount' => 4000, 'date' => '2026-01-10']);
        Transaction::factory()->for($this->user)->for($this->account)->for($expenseCategory)
            ->create(['amount' => 1000, 'date' => '2026-01-20']);

        // Balance at Jan 15 should include income but not the Jan 20 expense
        $at      = Carbon::parse('2026-01-15');
        $balance = $this->service->computeBalanceAt($this->account, $at);

        $this->assertEquals(14000.0, $balance); // 10000 + 4000
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_excludes_pending_income_when_computing_balance_at()
    {
        $incomeCategory = $this->createCategory('Salary', 'income');

        Transaction::factory()->for($this->user)->for($this->account)->for($incomeCategory)
            ->create([
                'amount'     => 5000,
                'date'       => '2026-01-05',
                'value_date' => '2026-02-01', // value date in the future relative to $at
            ]);

        $at      = Carbon::parse('2026-01-31');
        $balance = $this->service->computeBalanceAt($this->account, $at);

        // Pending income must NOT be counted
        $this->assertEquals(10000.0, $balance);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_transfers_in_computeBalanceAt()
    {
        $otherAccount = Account::factory()->for($this->user)->create(['type' => 'cash']);

        Transfer::create([
            'user_id'         => $this->user->id,
            'from_account_id' => $otherAccount->id,
            'to_account_id'   => $this->account->id,
            'amount'          => 3000,
            'date'            => '2026-01-10',
        ]);

        Transfer::create([
            'user_id'         => $this->user->id,
            'from_account_id' => $this->account->id,
            'to_account_id'   => $otherAccount->id,
            'amount'          => 1000,
            'date'            => '2026-01-15',
        ]);

        $at      = Carbon::parse('2026-01-31');
        $balance = $this->service->computeBalanceAt($this->account, $at);

        $this->assertEquals(12000.0, $balance); // 10000 + 3000 − 1000
    }

    // ────────────────────────────────────────────────────────────────────────
    // HELPER
    // ────────────────────────────────────────────────────────────────────────

    private function createCategory(string $name, string $type): Category
    {
        return Category::factory()
            ->for($this->user)
            ->create(['name' => $name, 'type' => $type]);
    }
}
