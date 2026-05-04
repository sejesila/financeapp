<?php

namespace Tests\Integration\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ReportDataService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportDataServiceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected ReportDataService $service;
    protected User $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = new ReportDataService();
        $this->user = User::factory()->create();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_complex_financial_scenario()
    {
        /**
         * Scenario: User with:
         * - Multiple income sources
         * - Various expense categories
         * - Active and paid loans
         * - Client fund transactions
         */

        $account = Account::factory()->for($this->user)->create(['current_balance' => 250000]);
        $savingsAccount = Account::factory()->for($this->user)->create(['current_balance' => 150000]);

        $priorYear = now()->subYear()->year;
        $startDate = Carbon::create($priorYear, 1, 1);

        // Income sources
        $salaryCategory = $this->createCategory('Salary', 'income');
        $freelanceCategory = $this->createCategory('Freelance', 'income');
        $clientCommissionCategory = $this->createCategory('Client Commission', 'income');

        // Expense categories
        $foodCategory = $this->createCategory('Food', 'expense');
        $transportCategory = $this->createCategory('Transport', 'expense');
        $loanRepaymentCategory = $this->createCategory('Loan Repayment', 'expense');

        // Create monthly salary (12 months x 100000)
        for ($month = 1; $month <= 12; $month++) {
            Transaction::factory()
                ->for($this->user)
                ->for($account)
                ->for($salaryCategory)
                ->create([
                    'type'   => 'income',
                    'amount' => 100000,
                    'date'   => $startDate->copy()->addMonth($month - 1),
                ]);
        }

        // Create freelance income (sporadic)
        Transaction::factory()
            ->for($this->user)
            ->for($account)
            ->for($freelanceCategory)
            ->create(['type' => 'income', 'amount' => 50000, 'date' => $startDate->copy()->addMonth(2)]);

        Transaction::factory()
            ->for($this->user)
            ->for($account)
            ->for($freelanceCategory)
            ->create(['type' => 'income', 'amount' => 75000, 'date' => $startDate->copy()->addMonth(6)]);

        // Create client commission income (with payment method)
        Transaction::factory()
            ->for($this->user)
            ->for($account)
            ->for($clientCommissionCategory)
            ->create([
                'type'           => 'income',
                'amount'         => 30000,
                'payment_method' => 'Client Commission',
                'date'           => $startDate->copy()->addMonth(4),
            ]);

        // Create client fund expense (should be excluded)
        Transaction::factory()
            ->for($this->user)
            ->for($account)
            ->for($foodCategory)
            ->create([
                'type'           => 'expense',
                'amount'         => 20000,
                'payment_method' => 'Client Fund',
                'date'           => $startDate->copy()->addMonth(3),
            ]);

        // Create regular expenses (should be included)
        for ($i = 0; $i < 12; $i++) {
            Transaction::factory()
                ->for($this->user)
                ->for($account)
                ->for($foodCategory)
                ->create([
                    'type'   => 'expense',
                    'amount' => 15000,
                    'date'   => $startDate->copy()->addMonth($i),
                ]);

            Transaction::factory()
                ->for($this->user)
                ->for($account)
                ->for($transportCategory)
                ->create([
                    'type'   => 'expense',
                    'amount' => 5000,
                    'date'   => $startDate->copy()->addMonth($i),
                ]);
        }

        // Create loan payments
        for ($i = 0; $i < 6; $i++) {
            Transaction::factory()
                ->for($this->user)
                ->for($account)
                ->for($loanRepaymentCategory)
                ->create([
                    'type'   => 'expense',
                    'amount' => 10000,
                    'date'   => $startDate->copy()->addMonth($i),
                ]);
        }

        // Create active loan
        $activeLoan = Loan::factory()
            ->for($this->user)
            ->for($account)
            ->create([
                'status'  => 'active',
                'balance' => 50000,
            ]);

        // Create paid loan (repaid during the year)
        $paidLoan = Loan::factory()
            ->for($this->user)
            ->for($account)
            ->create([
                'status'           => 'paid',
                'principal_amount' => 100000,
                'total_amount'     => 110000,
                'repaid_date'      => $startDate->copy()->addMonth(8),
            ]);

        // Generate report
        $report = $this->service->generateAnnualReport($this->user);

        // Assertions
        // Income: 12x100000 (salary) + 50000 + 75000 (freelance) + 30000 (commission) = 1,355,000
        $this->assertEquals(1355000, $report['income']);

        // Expenses: Should exclude the 20000 client fund transaction
        // Food: 12x15000 = 180000
        // Transport: 12x5000 = 60000
        // Loan Repayment: 6x10000 = 60000
        // Total: 300000 (not including the 20000 client fund)
        $this->assertEquals(300000, $report['expenses']);

        // Net flow
        $this->assertEquals(1055000, $report['net_flow']);

        // Savings rate
        $expectedSavingsRate = (1055000 / 1355000) * 100;
        $this->assertEqualsWithDelta($expectedSavingsRate, $report['savings_rate'], 0.1);

        // Active loans
        $this->assertEquals(50000, $report['total_loans']);

        // Net worth (accounts: 250000 + 150000 = 400000, loans: 50000)
        $this->assertEquals(350000, $report['net_worth']);

        // Loans repaid
        $this->assertEquals(1, $report['loans_repaid_in_period']['count']);
        $this->assertEquals(110000, $report['loans_repaid_in_period']['total']);

        // Loan payments
        $this->assertEquals(6, $report['loans_paid_in_period']['count']);
        $this->assertEquals(60000, $report['loans_paid_in_period']['total']);

        // Profitable months (all 12 months should be profitable with this scenario)
        $this->assertEquals(12, $report['profitable_months']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_correctly_filters_transactions_from_multiple_users()
    {
        $user2 = User::factory()->create();
        $account1 = Account::factory()->for($this->user)->create();
        $account2 = Account::factory()->for($user2)->create();

        $category = $this->createCategory('Income', 'income');

        $startDate = now()->startOfMonth();

        // Create income for user 1
        Transaction::factory()
            ->for($this->user)
            ->for($account1)
            ->for($category)
            ->create(['type' => 'income', 'amount' => 50000, 'date' => $startDate]);

        // Create income for user 2
        Transaction::factory()
            ->for($user2)
            ->for($account2)
            ->for($category)
            ->create(['type' => 'income', 'amount' => 100000, 'date' => $startDate]);

        $report1 = $this->service->generateMonthlyReport($this->user);
        $report2 = $this->service->generateMonthlyReport($user2);

        // Each user should only see their own transactions
        $this->assertEquals(50000, $report1['income']);
        $this->assertEquals(100000, $report2['income']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_date_filtering_correctly()
    {
        $account = Account::factory()->for($this->user)->create();
        $category = $this->createCategory('Income', 'income');

        $startDate = Carbon::create(2024, 1, 1);
        $endDate = Carbon::create(2024, 3, 31);

        // Create income in the range
        Transaction::factory()
            ->for($this->user)
            ->for($account)
            ->for($category)
            ->create(['type' => 'income', 'amount' => 50000, 'date' => $startDate->copy()->addDays(5)]);

        // Create income outside the range (before)
        Transaction::factory()
            ->for($this->user)
            ->for($account)
            ->for($category)
            ->create(['type' => 'income', 'amount' => 25000, 'date' => $startDate->copy()->subDays(10)]);

        // Create income outside the range (after)
        Transaction::factory()
            ->for($this->user)
            ->for($account)
            ->for($category)
            ->create(['type' => 'income', 'amount' => 75000, 'date' => $endDate->copy()->addDays(10)]);

        $report = $this->service->generateCustomReport($this->user, $startDate, $endDate);

        // Should only count the transaction within the range
        $this->assertEquals(50000, $report['income']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_period_date_field_correctly()
    {
        /**
         * Test that period_date takes precedence over date field
         * when both are present
         */
        $account = Account::factory()->for($this->user)->create();
        $category = $this->createCategory('Income', 'income');

        $startDate = Carbon::create(2024, 1, 1);
        $endDate = Carbon::create(2024, 1, 31);

        // Create transaction with period_date in range, date outside range
        Transaction::factory()
            ->for($this->user)
            ->for($account)
            ->for($category)
            ->create([
                'type'        => 'income',
                'amount'      => 50000,
                'date'        => $startDate->copy()->subMonth(), // Outside range
                'period_date' => $startDate->copy()->addDays(5), // In range
            ]);

        $report = $this->service->generateCustomReport($this->user, $startDate, $endDate);

        // Should be included because period_date is in range
        $this->assertEquals(50000, $report['income']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_generates_accurate_daily_spending_data()
    {
        $account = Account::factory()->for($this->user)->create();
        $category = $this->createCategory('Food', 'expense');

        $startDate = now()->startOfMonth();

        // Create expenses on different days
        Transaction::factory()
            ->for($this->user)
            ->for($account)
            ->for($category)
            ->create(['type' => 'expense', 'amount' => 1000, 'date' => $startDate->copy()->addDays(0)]);

        Transaction::factory()
            ->for($this->user)
            ->for($account)
            ->for($category)
            ->create(['type' => 'expense', 'amount' => 2000, 'date' => $startDate->copy()->addDays(0)]);

        Transaction::factory()
            ->for($this->user)
            ->for($account)
            ->for($category)
            ->create(['type' => 'expense', 'amount' => 1500, 'date' => $startDate->copy()->addDays(1)]);

        $report = $this->service->generateMonthlyReport($this->user);

        // Should have 2 days of spending
        $this->assertCount(2, $report['daily_spending']);
        $this->assertEquals(3000, $report['daily_spending'][0]['amount']); // Day 1: 1000 + 2000
        $this->assertEquals(1500, $report['daily_spending'][1]['amount']); // Day 2: 1500
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_empty_report_gracefully()
    {
        $startDate = now()->startOfMonth();
        $endDate = now()->endOfMonth();

        // No transactions created
        $report = $this->service->generateCustomReport($this->user, $startDate, $endDate);

        $this->assertEquals(0, $report['income']);
        $this->assertEquals(0, $report['expenses']);
        $this->assertEquals(0, $report['net_flow']);
        $this->assertEquals(0, $report['savings_rate']);
        $this->assertEmpty($report['top_categories']);
        $this->assertEmpty($report['largest_transactions']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_calculates_savings_rate_with_no_income()
    {
        $account = Account::factory()->for($this->user)->create();
        $category = $this->createCategory('Food', 'expense');

        $startDate = now()->startOfMonth();

        // Create only expenses
        Transaction::factory()
            ->for($this->user)
            ->for($account)
            ->for($category)
            ->create(['type' => 'expense', 'amount' => 5000, 'date' => $startDate]);

        $report = $this->service->generateMonthlyReport($this->user);

        $this->assertEquals(0, $report['income']);
        $this->assertEquals(5000, $report['expenses']);
        $this->assertEquals(0, $report['savings_rate']); // Should be 0, not infinite or error
    }

    // ────────────────────────────────────────────────────────────────────────
    // HELPER METHODS
    // ────────────────────────────────────────────────────────────────────────

    private function createCategory(string $name, string $type): Category
    {
        return Category::factory()
            ->for($this->user)
            ->create(['name' => $name, 'type' => $type]);
    }
}
