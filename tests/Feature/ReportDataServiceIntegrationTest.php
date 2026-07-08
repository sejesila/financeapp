<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ReportDataService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        // 'type' is pinned explicitly on both accounts (not left to the
        // factory's default/random value) because this test's net_worth
        // assertion depends on neither account being type 'savings'.
        // Without pinning this, the factory's default type can vary between
        // runs and make the net_worth assertion flaky. ('$savingsAccount' is
        // just a variable name here — it is NOT actually a savings-type
        // account. See it_calculates_positive_net_worth_with_a_true_savings_account
        // for the case where an account genuinely has type 'savings'.)
        $account = Account::factory()->for($this->user)->create([
            'current_balance' => 250000,
            'initial_balance' => 250000,
            'type'            => 'mpesa',
        ]);
        $savingsAccount = Account::factory()->for($this->user)->create([
            'current_balance' => 150000,
            'initial_balance' => 150000,
            'type'            => 'bank',
        ]);

        $priorYear = now()->subYear()->year;
        $startDate = Carbon::create($priorYear, 1, 1);

        // Income sources
        $salaryCategory           = $this->createCategory('Salary', 'income');
        $freelanceCategory        = $this->createCategory('Freelance', 'income');
        $clientCommissionCategory = $this->createCategory('Client Commission', 'income');

        // Expense categories
        $foodCategory          = $this->createCategory('Food', 'expense');
        $transportCategory     = $this->createCategory('Transport', 'expense');
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
            ->for($this->user)->for($account)->for($freelanceCategory)
            ->create(['type' => 'income', 'amount' => 50000, 'date' => $startDate->copy()->addMonth(2)]);

        Transaction::factory()
            ->for($this->user)->for($account)->for($freelanceCategory)
            ->create(['type' => 'income', 'amount' => 75000, 'date' => $startDate->copy()->addMonth(6)]);

        // Create client commission income
        Transaction::factory()
            ->for($this->user)->for($account)->for($clientCommissionCategory)
            ->create([
                'type'           => 'income',
                'amount'         => 30000,
                'payment_method' => 'Client Commission',
                'date'           => $startDate->copy()->addMonth(4),
            ]);

        // Create client fund expense (should be excluded from report)
        Transaction::factory()
            ->for($this->user)->for($account)->for($foodCategory)
            ->create([
                'type'           => 'expense',
                'amount'         => 20000,
                'payment_method' => 'Client Fund',
                'date'           => $startDate->copy()->addMonth(3),
            ]);

        // Create regular expenses
        for ($i = 0; $i < 12; $i++) {
            Transaction::factory()
                ->for($this->user)->for($account)->for($foodCategory)
                ->create(['type' => 'expense', 'amount' => 15000, 'date' => $startDate->copy()->addMonth($i)]);

            Transaction::factory()
                ->for($this->user)->for($account)->for($transportCategory)
                ->create(['type' => 'expense', 'amount' => 5000, 'date' => $startDate->copy()->addMonth($i)]);
        }

        // Loan being paid down over the year. getLoanPaymentsInPeriod() now
        // reads from LoanPayment (the authoritative record), not by matching
        // a Transaction's category name — so each repayment below needs a
        // real Loan behind it, not just a "Loan Repayment"-categorized
        // transaction. balance is pinned to 0 (fully paid down, status left
        // as 'active') specifically so it contributes nothing extra to
        // total_loans / loans_repaid_in_period, keeping those assertions
        // below unchanged from before this fix.
        $repaymentLoan = Loan::factory()->for($this->user)->for($account)->create([
            'status'           => 'active',
            'principal_amount' => 60000,
            'total_amount'     => 60000,
            'balance'          => 0,
            'amount_paid'      => 60000,
        ]);

        // Create loan payments — each backed by both a Transaction (so it
        // still counts as ordinary expense spend in `expenses`) and a
        // LoanPayment (so it's recognized as a real repayment).
        for ($i = 0; $i < 6; $i++) {
            $repaymentTransaction = Transaction::factory()
                ->for($this->user)->for($account)->for($loanRepaymentCategory)
                ->create(['type' => 'expense', 'amount' => 10000, 'date' => $startDate->copy()->addMonth($i)]);

            LoanPayment::create([
                'user_id'           => $this->user->id,
                'loan_id'           => $repaymentLoan->id,
                'account_id'        => $account->id,
                'amount'            => 10000,
                'principal_portion' => 10000,
                'interest_portion'  => 0,
                'payment_date'      => $startDate->copy()->addMonth($i),
                'transaction_id'    => $repaymentTransaction->id,
            ]);
        }

        // Create active loan
        Loan::factory()->for($this->user)->for($account)->create([
            'status'  => 'active',
            'balance' => 50000,
        ]);

        // Create paid loan (repaid during the year)
        Loan::factory()->for($this->user)->for($account)->create([
            'status'           => 'paid',
            'principal_amount' => 100000,
            'total_amount'     => 110000,
            'repaid_date'      => $startDate->copy()->addMonth(8),
        ]);

        // Reset balances to known values — updateBalance() fires during factory
        // creation and overwrites the seeded current_balance. We pin them back
        // so the report figures are deterministic regardless of balance-hook behaviour.
        DB::statement('UPDATE accounts SET current_balance = 250000 WHERE id = ?', [$account->id]);
        DB::statement('UPDATE accounts SET current_balance = 150000 WHERE id = ?', [$savingsAccount->id]);

        $report = $this->service->generateAnnualReport($this->user);

        // Income: 12x100000 (salary) + 50000 + 75000 (freelance) + 30000 (commission) = 1,355,000
        $this->assertEquals(1355000, $report['income']);

        // Expenses: client fund (20000) excluded
        // Food: 12x15000 = 180000 | Transport: 12x5000 = 60000 | Loan Repayment: 6x10000 = 60000
        $this->assertEquals(300000, $report['expenses']);

        $this->assertEquals(1055000, $report['net_flow']);

        $expectedSavingsRate = (1055000 / 1355000) * 100;
        $this->assertEqualsWithDelta($expectedSavingsRate, $report['savings_rate'], 0.1);

        // total_loans sums balance across ACTIVE loans only: 50000 (the
        // standalone active loan) + 0 ($repaymentLoan, fully paid down).
        $this->assertEquals(50000, $report['total_loans']);

        // net_worth is derived from getSavingsBalanceAsAt(), which only sums
        // accounts with type === 'savings'. Neither $account nor
        // $savingsAccount here is created with type 'savings' (both are
        // plain factory accounts, and 'savingsAccount' is just a variable
        // name — it doesn't set the type column). So the savings balance is
        // 0, and net_worth clamps to 0 regardless of loans/client funds.
        $this->assertEquals(0, $report['net_worth']);

        // Loans repaid during the year — only the standalone 'paid' loan
        // counts here; $repaymentLoan is left 'active' so it doesn't
        // double up on this assertion.
        $this->assertEquals(1,      $report['loans_repaid_in_period']['count']);
        $this->assertEquals(110000, $report['loans_repaid_in_period']['total']);

        // Loan repayment transactions — now sourced from the 6 LoanPayment
        // records tied to $repaymentLoan.
        $this->assertEquals(6,     $report['loans_paid_in_period']['count']);
        $this->assertEquals(60000, $report['loans_paid_in_period']['total']);

        // All 12 months profitable (100k salary far exceeds monthly expenses)
        $this->assertEquals(12, $report['profitable_months']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_calculates_positive_net_worth_with_a_true_savings_account()
    {
        // Companion to it_handles_complex_financial_scenario: exercises the
        // intended positive-net-worth path by actually setting type =>
        // 'savings' on the account, so it is picked up by
        // getSavingsBalanceAsAt().
        $savingsAccount = Account::factory()->for($this->user)->create([
            'type'            => 'savings',
            'current_balance' => 150000,
        ]);

        Loan::factory()->for($this->user)->for($savingsAccount)->create([
            'status'  => 'active',
            'balance' => 50000,
        ]);

        $report = $this->service->generateAnnualReport($this->user);

        $this->assertEquals(50000, $report['total_loans']);
        $this->assertEquals(100000, $report['net_worth']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_correctly_filters_transactions_from_multiple_users()
    {
        $user2 = User::factory()->create();
        $account1 = Account::factory()->for($this->user)->create();
        $account2 = Account::factory()->for($user2)->create();

        $category = $this->createCategory('Income', 'income');

        $startDate = now()->subMonth()->startOfMonth();

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
    public function it_ignores_period_date_and_filters_on_date()
    {
        /**
         * The service's getFilteredTransactions() filters exclusively on
         * the `date` column; `period_date` plays no role in inclusion.
         */
        $account = Account::factory()->for($this->user)->create();
        $category = $this->createCategory('Income', 'income');

        $startDate = Carbon::create(2024, 1, 1);
        $endDate = Carbon::create(2024, 1, 31);

        // date is outside the range; period_date is inside the range —
        // this transaction should still be EXCLUDED, since period_date
        // is not consulted by the filter.
        Transaction::factory()
            ->for($this->user)
            ->for($account)
            ->for($category)
            ->create([
                'type'        => 'income',
                'amount'      => 50000,
                'date'        => $startDate->copy()->subMonth(),  // Outside range
                'period_date' => $startDate->copy()->addDays(5),  // In range, but irrelevant
            ]);

        $report = $this->service->generateCustomReport($this->user, $startDate, $endDate);

        $this->assertEquals(0, $report['income']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_generates_accurate_daily_spending_data()
    {
        $account = Account::factory()->for($this->user)->create();
        $category = $this->createCategory('Food', 'expense');

        $startDate = now()->subMonth()->startOfMonth();

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

        $startDate = now()->subMonth()->startOfMonth();

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
