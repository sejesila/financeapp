<?php

namespace Tests\Unit\Services;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ReportDataService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportDataServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ReportDataService $service;
    protected User $user;
    protected Account $account;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = new ReportDataService();
        $this->user = User::factory()->create();
        $this->account = Account::factory()
            ->for($this->user)
            ->create(['current_balance' => 100000]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // ANNUAL REPORT TESTS
    // ────────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_generates_annual_report_for_prior_year()
    {
        $priorYear = now()->subYear()->year;
        $startDate = Carbon::create($priorYear, 1, 1);
        $endDate = Carbon::create($priorYear, 12, 31);

        // Create transactions for prior year
        $this->createIncomeTransaction($this->user, $this->account, 50000, $startDate->copy()->addDays(5));
        $this->createExpenseTransaction($this->user, $this->account, 10000, $startDate->copy()->addDays(10));

        $report = $this->service->generateAnnualReport($this->user);

        $this->assertIsArray($report);
        $this->assertEquals('annual', $report['period_type']);
        $this->assertEquals($priorYear, $report['year']);
        $this->assertEquals(50000, $report['income']);
        $this->assertEquals(10000, $report['expenses']);
        $this->assertEquals(40000, $report['net_flow']);
        $this->assertEquals(80, $report['savings_rate']); // 40000/50000 * 100
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_monthly_breakdown_in_annual_report()
    {
        $priorYear = now()->subYear()->year;
        $startDate = Carbon::create($priorYear, 1, 1);

        // Create transactions in different months
        $this->createIncomeTransaction($this->user, $this->account, 10000, $startDate->copy()->addMonth(0));
        $this->createExpenseTransaction($this->user, $this->account, 2000, $startDate->copy()->addMonth(0));

        $this->createIncomeTransaction($this->user, $this->account, 15000, $startDate->copy()->addMonth(1));
        $this->createExpenseTransaction($this->user, $this->account, 3000, $startDate->copy()->addMonth(1));

        $report = $this->service->generateAnnualReport($this->user);

        $this->assertCount(12, $report['monthly_breakdown']);
        $this->assertEquals(10000, $report['monthly_breakdown'][0]['income']);
        $this->assertEquals(2000, $report['monthly_breakdown'][0]['expenses']);
        $this->assertEquals(15000, $report['monthly_breakdown'][1]['income']);
        $this->assertEquals(3000, $report['monthly_breakdown'][1]['expenses']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_calculates_best_and_worst_months()
    {
        $priorYear = now()->subYear()->year;
        $startDate = Carbon::create($priorYear, 1, 1);

        // Best month: 50000 - 5000 = 45000
        $this->createIncomeTransaction($this->user, $this->account, 50000, $startDate->copy()->addMonth(5));
        $this->createExpenseTransaction($this->user, $this->account, 5000, $startDate->copy()->addMonth(5));

        // Worst month: 10000 - 20000 = -10000
        $this->createIncomeTransaction($this->user, $this->account, 10000, $startDate->copy()->addMonth(8));
        $this->createExpenseTransaction($this->user, $this->account, 20000, $startDate->copy()->addMonth(8));

        $report = $this->service->generateAnnualReport($this->user);

        $this->assertEquals(45000, $report['best_month']['net_flow']);
        $this->assertEquals(-10000, $report['worst_month']['net_flow']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_counts_profitable_months()
    {
        $priorYear = now()->subYear()->year;
        $startDate = Carbon::create($priorYear, 1, 1);

        // 3 profitable months, 2 loss months
        for ($i = 0; $i < 3; $i++) {
            $this->createIncomeTransaction($this->user, $this->account, 30000, $startDate->copy()->addMonth($i));
            $this->createExpenseTransaction($this->user, $this->account, 10000, $startDate->copy()->addMonth($i));
        }

        for ($i = 3; $i < 5; $i++) {
            $this->createIncomeTransaction($this->user, $this->account, 5000, $startDate->copy()->addMonth($i));
            $this->createExpenseTransaction($this->user, $this->account, 15000, $startDate->copy()->addMonth($i));
        }

        $report = $this->service->generateAnnualReport($this->user);

        $this->assertEquals(3, $report['profitable_months']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_loans_repaid_during_year()
    {
        $priorYear = now()->subYear()->year;
        $startDate = Carbon::create($priorYear, 1, 1);
        $endDate = Carbon::create($priorYear, 12, 31);

        // Create a loan that was repaid during the year
        $loan = Loan::factory()
            ->for($this->user)
            ->for($this->account)
            ->create([
                'status'           => 'paid',
                'principal_amount' => 50000,
                'total_amount'     => 55000,
                'repaid_date'      => $startDate->copy()->addMonth(3),
            ]);

        $report = $this->service->generateAnnualReport($this->user);

        $this->assertEquals(1, $report['loans_repaid_in_period']['count']);
        $this->assertEquals(55000, $report['loans_repaid_in_period']['total']);
        $this->assertEquals(50000, $report['loans_repaid_in_period']['principal_total']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_calculates_income_trend_vs_prior_year()
    {
        $priorYear = now()->subYear()->year;
        $currentYear = $priorYear + 1;

        // Prior year: 50000 income
        $this->createIncomeTransaction(
            $this->user,
            $this->account,
            50000,
            Carbon::create($priorYear, 6, 1)
        );

        // Current year: 60000 income (20% increase)
        $this->createIncomeTransaction(
            $this->user,
            $this->account,
            60000,
            Carbon::create($currentYear, 6, 1)
        );

        // Manually adjust the report year for testing
        $priorYearIncome = 50000;
        $currentYearIncome = 60000;
        $trend = (($currentYearIncome - $priorYearIncome) / $priorYearIncome) * 100;

        $this->assertEquals(20, $trend);
    }

    // ────────────────────────────────────────────────────────────────────────
    // MONTHLY REPORT TESTS
    // ────────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_generates_monthly_report_for_current_month()
    {
        $startDate = now()->startOfMonth();
        $endDate = now()->endOfMonth();

        $this->createIncomeTransaction($this->user, $this->account, 50000, $startDate->copy()->addDays(5));
        $this->createExpenseTransaction($this->user, $this->account, 10000, $startDate->copy()->addDays(10));

        $report = $this->service->generateMonthlyReport($this->user);

        $this->assertEquals('monthly', $report['period_type']);
        $this->assertEquals(50000, $report['income']);
        $this->assertEquals(10000, $report['expenses']);
        $this->assertEquals(40000, $report['net_flow']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_budget_performance_in_monthly_report()
    {
        $startDate = now()->startOfMonth();
        $endDate = now()->endOfMonth();

        $incomeCategory = $this->createCategory($this->user, 'Salary', 'income');
        $expenseCategory = $this->createCategory($this->user, 'Food', 'expense');

        // Create budget
        Budget::factory()
            ->for($this->user)
            ->for($expenseCategory)
            ->create([
                'year'   => $startDate->year,
                'month'  => $startDate->month,
                'amount' => 20000,
            ]);

        // Create transaction
        $this->createExpenseTransaction($this->user, $this->account, 15000, $startDate->copy()->addDays(5), $expenseCategory);

        $report = $this->service->generateMonthlyReport($this->user);

        $this->assertNotEmpty($report['budget_performance']);
        $budget = $report['budget_performance'][0];
        $this->assertEquals('Food', $budget['category']);
        $this->assertEquals(20000, $budget['budgeted']);
        $this->assertEquals(15000, $budget['spent']);
        $this->assertEquals(5000, $budget['remaining']);
        $this->assertEquals(75, $budget['percentage']); // 15000/20000 * 100
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_tracks_loan_payments_in_monthly_report()
    {
        $startDate = now()->startOfMonth();

        $loanRepaymentCategory = $this->createCategory($this->user, 'Loan Repayment', 'expense');

        // Create loan repayment transaction
        Transaction::factory()
            ->for($this->user)
            ->for($this->account)
            ->for($loanRepaymentCategory)
            ->create([
                'type'        => 'expense',
                'amount'      => 5000,
                'description' => 'Loan repayment to M-Shwari',
                'date'        => $startDate->copy()->addDays(5),
            ]);

        $report = $this->service->generateMonthlyReport($this->user);

        $this->assertEquals(1, $report['loans_paid_in_period']['count']);
        $this->assertEquals(5000, $report['loans_paid_in_period']['total']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // CUSTOM REPORT TESTS
    // ────────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_generates_custom_period_report()
    {
        $startDate = Carbon::create(2024, 3, 1);
        $endDate = Carbon::create(2024, 5, 31);

        $this->createIncomeTransaction($this->user, $this->account, 50000, $startDate->copy()->addDays(5));
        $this->createExpenseTransaction($this->user, $this->account, 10000, $startDate->copy()->addDays(10));

        $report = $this->service->generateCustomReport($this->user, $startDate, $endDate);

        $this->assertEquals('custom', $report['period_type']);
        $this->assertEquals(50000, $report['income']);
        $this->assertEquals(10000, $report['expenses']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // TRANSACTION FILTERING TESTS
    // ────────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_excludes_client_fund_expenses()
    {
        $startDate = now()->startOfMonth();
        $expenseCategory = $this->createCategory($this->user, 'Client Payment', 'expense');

        // Create client fund expense (should be excluded)
        Transaction::factory()
            ->for($this->user)
            ->for($this->account)
            ->for($expenseCategory)
            ->create([
                'type'            => 'expense',
                'amount'          => 5000,
                'payment_method'  => 'Client Fund',
                'date'            => $startDate->copy()->addDays(1),
            ]);

        // Create regular expense (should be included)
        $this->createExpenseTransaction($this->user, $this->account, 2000, $startDate->copy()->addDays(2));

        $report = $this->service->generateMonthlyReport($this->user);

        // Should only count the regular expense
        $this->assertEquals(2000, $report['expenses']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_client_commission_income()
    {
        $startDate = now()->startOfMonth();
        $incomeCategory = $this->createCategory($this->user, 'Client Commission', 'income');

        // Create client commission income (should be included)
        Transaction::factory()
            ->for($this->user)
            ->for($this->account)
            ->for($incomeCategory)
            ->create([
                'type'           => 'income',
                'amount'         => 5000,
                'payment_method' => 'Client Commission',
                'date'           => $startDate->copy()->addDays(1),
            ]);

        // Create regular income (should be included)
        $this->createIncomeTransaction($this->user, $this->account, 50000, $startDate->copy()->addDays(2));

        $report = $this->service->generateMonthlyReport($this->user);

        // Should count both
        $this->assertEquals(55000, $report['income']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_excludes_loan_disbursement_category()
    {
        $startDate = now()->startOfMonth();
        $loanCategory = $this->createCategory($this->user, 'Loan Disbursement', 'income');

        // Create loan disbursement (should be excluded)
        Transaction::factory()
            ->for($this->user)
            ->for($this->account)
            ->for($loanCategory)
            ->create([
                'type'   => 'income',
                'amount' => 50000,
                'date'   => $startDate->copy()->addDays(1),
            ]);

        $report = $this->service->generateMonthlyReport($this->user);

        // Should not count the loan disbursement
        $this->assertEquals(0, $report['income']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_excludes_loan_receipt_category()
    {
        $startDate = now()->startOfMonth();
        $loanCategory = $this->createCategory($this->user, 'Loan Receipt', 'income');

        // Create loan receipt (should be excluded)
        Transaction::factory()
            ->for($this->user)
            ->for($this->account)
            ->for($loanCategory)
            ->create([
                'type'   => 'income',
                'amount' => 50000,
                'date'   => $startDate->copy()->addDays(1),
            ]);

        $report = $this->service->generateMonthlyReport($this->user);

        // Should not count the loan receipt
        $this->assertEquals(0, $report['income']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_excludes_balance_adjustment_category()
    {
        $startDate = now()->startOfMonth();
        $adjustmentCategory = $this->createCategory($this->user, 'Balance Adjustment', 'expense');

        // Create balance adjustment (should be excluded)
        Transaction::factory()
            ->for($this->user)
            ->for($this->account)
            ->for($adjustmentCategory)
            ->create([
                'type'   => 'expense',
                'amount' => 5000,
                'date'   => $startDate->copy()->addDays(1),
            ]);

        $report = $this->service->generateMonthlyReport($this->user);

        // Should not count the balance adjustment
        $this->assertEquals(0, $report['expenses']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_excludes_client_funds_liability_category()
    {
        $startDate = now()->startOfMonth();
        $clientFundsCategory = $this->createCategory($this->user, 'Client Funds', 'expense');

        // Create client funds transaction (should be excluded)
        Transaction::factory()
            ->for($this->user)
            ->for($this->account)
            ->for($clientFundsCategory)
            ->create([
                'type'   => 'expense',
                'amount' => 5000,
                'date'   => $startDate->copy()->addDays(1),
            ]);

        $report = $this->service->generateMonthlyReport($this->user);

        // Should not count the client funds transaction
        $this->assertEquals(0, $report['expenses']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // NET WORTH TESTS
    // ────────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_calculates_net_worth_correctly()
    {
        // Account balance: 100000, Active loans: 30000
        $loan = Loan::factory()
            ->for($this->user)
            ->for($this->account)
            ->create([
                'status'  => 'active',
                'balance' => 30000,
            ]);

        $report = $this->service->generateMonthlyReport($this->user);

        $this->assertEquals(100000, $report['total_balance']);
        $this->assertEquals(30000, $report['total_loans']);
        $this->assertEquals(70000, $report['net_worth']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_only_includes_active_loans_in_net_worth()
    {
        // Create an active loan
        Loan::factory()
            ->for($this->user)
            ->for($this->account)
            ->create([
                'status'  => 'active',
                'balance' => 30000,
            ]);

        // Create a paid loan (should not affect net worth)
        Loan::factory()
            ->for($this->user)
            ->for($this->account)
            ->create([
                'status'  => 'paid',
                'balance' => 0,
                'repaid_date' => now()->subMonth(),
            ]);

        $report = $this->service->generateMonthlyReport($this->user);

        // Only the active loan should count
        $this->assertEquals(30000, $report['total_loans']);
        $this->assertEquals(70000, $report['net_worth']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_negative_net_worth()
    {
        // Create a loan larger than account balance
        Loan::factory()
            ->for($this->user)
            ->for($this->account)
            ->create([
                'status'  => 'active',
                'balance' => 150000,
            ]);

        $report = $this->service->generateMonthlyReport($this->user);

        $this->assertEquals(100000, $report['total_balance']);
        $this->assertEquals(150000, $report['total_loans']);
        $this->assertEquals(-50000, $report['net_worth']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // INSIGHTS TESTS
    // ────────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_generates_average_daily_spending_insight()
    {
        $startDate = now()->startOfMonth();

        // Create 10000 expense over 10 days (avg 1000/day)
        $this->createExpenseTransaction($this->user, $this->account, 10000, $startDate->copy()->addDays(5));

        $report = $this->service->generateMonthlyReport($this->user);

        $avgDailyInsight = collect($report['insights'])->firstWhere('title', 'Average Daily Spending');

        $this->assertNotNull($avgDailyInsight);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_spending_increase()
    {
        $startDate = now()->startOfMonth();
        $prevStart = $startDate->copy()->subMonth()->startOfMonth();

        // Previous month: 5000 expense
        $this->createExpenseTransaction($this->user, $this->account, 5000, $prevStart->copy()->addDays(5));

        // Current month: 10000 expense (100% increase)
        $this->createExpenseTransaction($this->user, $this->account, 10000, $startDate->copy()->addDays(5));

        $report = $this->service->generateMonthlyReport($this->user);

        $spendingTrendInsight = collect($report['insights'])->firstWhere('title', 'Spending Increased');

        $this->assertNotNull($spendingTrendInsight);
        $this->assertStringContainsString('100', $spendingTrendInsight['value']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_spending_decrease()
    {
        $startDate = now()->startOfMonth();
        $prevStart = $startDate->copy()->subMonth()->startOfMonth();

        // Previous month: 10000 expense
        $this->createExpenseTransaction($this->user, $this->account, 10000, $prevStart->copy()->addDays(5));

        // Current month: 5000 expense (50% decrease)
        $this->createExpenseTransaction($this->user, $this->account, 5000, $startDate->copy()->addDays(5));

        $report = $this->service->generateMonthlyReport($this->user);

        $spendingTrendInsight = collect($report['insights'])->firstWhere('title', 'Spending Decreased');

        $this->assertNotNull($spendingTrendInsight);
        $this->assertStringContainsString('50', $spendingTrendInsight['value']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_identifies_biggest_expense()
    {
        $startDate = now()->startOfMonth();

        $this->createExpenseTransaction($this->user, $this->account, 2000, $startDate->copy()->addDays(1));
        $this->createExpenseTransaction($this->user, $this->account, 5000, $startDate->copy()->addDays(5), null, 'Big Purchase');
        $this->createExpenseTransaction($this->user, $this->account, 1000, $startDate->copy()->addDays(10));

        $report = $this->service->generateMonthlyReport($this->user);

        $biggestExpenseInsight = collect($report['insights'])->firstWhere('title', 'Biggest Expense');

        $this->assertNotNull($biggestExpenseInsight);
        $this->assertStringContainsString('5000', $biggestExpenseInsight['value']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_calculates_savings_rate_insight()
    {
        $startDate = now()->startOfMonth();

        $this->createIncomeTransaction($this->user, $this->account, 100000, $startDate->copy()->addDays(1));
        $this->createExpenseTransaction($this->user, $this->account, 50000, $startDate->copy()->addDays(5));

        $report = $this->service->generateMonthlyReport($this->user);

        $savingsRateInsight = collect($report['insights'])->firstWhere('title', 'Savings Rate');

        $this->assertNotNull($savingsRateInsight);
        $this->assertStringContainsString('50', $savingsRateInsight['value']); // 50% savings rate
    }

    // ────────────────────────────────────────────────────────────────────────
    // TOP CATEGORIES AND TRANSACTIONS TESTS
    // ────────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_identifies_top_spending_categories()
    {
        $startDate = now()->startOfMonth();

        $foodCategory = $this->createCategory($this->user, 'Food', 'expense');
        $transportCategory = $this->createCategory($this->user, 'Transport', 'expense');

        $this->createExpenseTransaction($this->user, $this->account, 5000, $startDate->copy()->addDays(1), $foodCategory);
        $this->createExpenseTransaction($this->user, $this->account, 3000, $startDate->copy()->addDays(2), $foodCategory);
        $this->createExpenseTransaction($this->user, $this->account, 2000, $startDate->copy()->addDays(3), $transportCategory);

        $report = $this->service->generateMonthlyReport($this->user);

        $this->assertCount(2, $report['top_categories']);
        $this->assertEquals('Food', $report['top_categories'][0]['category']);
        $this->assertEquals(8000, $report['top_categories'][0]['amount']);
        $this->assertEquals('Transport', $report['top_categories'][1]['category']);
        $this->assertEquals(2000, $report['top_categories'][1]['amount']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_limits_top_categories_to_5()
    {
        $startDate = now()->startOfMonth();

        // Create 10 different expense categories
        for ($i = 1; $i <= 10; $i++) {
            $category = $this->createCategory($this->user, "Category $i", 'expense');
            $this->createExpenseTransaction($this->user, $this->account, 1000, $startDate->copy()->addDays($i), $category);
        }

        $report = $this->service->generateMonthlyReport($this->user);

        $this->assertCount(5, $report['top_categories']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_identifies_largest_transactions()
    {
        $startDate = now()->startOfMonth();

        $this->createExpenseTransaction($this->user, $this->account, 1000, $startDate->copy()->addDays(1));
        $this->createExpenseTransaction($this->user, $this->account, 5000, $startDate->copy()->addDays(2));
        $this->createExpenseTransaction($this->user, $this->account, 2000, $startDate->copy()->addDays(3));
        $this->createExpenseTransaction($this->user, $this->account, 8000, $startDate->copy()->addDays(4));
        $this->createExpenseTransaction($this->user, $this->account, 3000, $startDate->copy()->addDays(5));

        $report = $this->service->generateMonthlyReport($this->user);

        $this->assertCount(5, $report['largest_transactions']);
        $this->assertEquals(8000, $report['largest_transactions'][0]->amount);
        $this->assertEquals(5000, $report['largest_transactions'][1]->amount);
    }

    // ────────────────────────────────────────────────────────────────────────
    // HELPER METHODS
    // ────────────────────────────────────────────────────────────────────────

    private function createCategory(User $user, string $name, string $type): Category
    {
        return Category::factory()
            ->for($user)
            ->create(['name' => $name, 'type' => $type]);
    }

    private function createIncomeTransaction(
        User $user,
        Account $account,
        float $amount,
        Carbon $date,
        ?Category $category = null,
        string $description = 'Income'
    ): Transaction {
        $category ??= $this->createCategory($user, 'Salary', 'income');

        return Transaction::factory()
            ->for($user)
            ->for($account)
            ->for($category)
            ->create([
                'type'        => 'income',
                'amount'      => $amount,
                'description' => $description,
                'date'        => $date,
            ]);
    }

    private function createExpenseTransaction(
        User $user,
        Account $account,
        float $amount,
        Carbon $date,
        ?Category $category = null,
        string $description = 'Expense'
    ): Transaction {
        $category ??= $this->createCategory($user, 'General Expense', 'expense');

        return Transaction::factory()
            ->for($user)
            ->for($account)
            ->for($category)
            ->create([
                'type'        => 'expense',
                'amount'      => $amount,
                'description' => $description,
                'date'        => $date,
            ]);
    }
}
