<?php

namespace Tests\Feature\Controllers;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BudgetControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    #[Test]
    public function unauthenticated_user_cannot_access_budget_index()
    {
        $response = $this->get(route('budgets.index'));
        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function authenticated_user_can_view_budget_index()
    {
        $response = $this->actingAs($this->user)->get(route('budgets.index'));
        $response->assertStatus(200);
        $response->assertViewIs('budgets.index');
    }

    #[Test]
    public function budget_index_uses_current_year_by_default()
    {
        $response = $this->actingAs($this->user)->get(route('budgets.index'));
        $response->assertViewHas('year', date('Y'));
    }

    #[Test]
    public function budget_index_accepts_custom_year()
    {
        $response = $this->actingAs($this->user)->get(route('budgets.index', ['year' => 2023]));
        $response->assertViewHas('year', '2023');
    }

    #[Test]
    public function budget_index_only_shows_categories_belonging_to_authenticated_user()
    {
        $otherUser = User::factory()->create();

        $myCategory = Category::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'income',
            'name' => 'My Salary',
        ]);

        $otherCategory = Category::factory()->create([
            'user_id' => $otherUser->id,
            'type' => 'income',
            'name' => 'Their Salary',
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $myCategory->id,
            'amount' => 1000,
            'date' => now(),
        ]);

        $response = $this->actingAs($this->user)->get(route('budgets.index'));

        $incomeCategories = $response->viewData('incomeCategories');
        $this->assertTrue($incomeCategories->contains('id', $myCategory->id));
        $this->assertFalse($incomeCategories->contains('id', $otherCategory->id));
    }

    #[Test]
    public function budget_index_excludes_loan_and_adjustment_categories_from_income()
    {
        $excluded = ['Loan Disbursement', 'Loan Receipt', 'Balance Adjustment'];

        foreach ($excluded as $name) {
            $category = Category::factory()->create([
                'user_id' => $this->user->id,
                'type' => 'income',
                'name' => $name,
            ]);

            Transaction::factory()->create([
                'user_id' => $this->user->id,
                'category_id' => $category->id,
                'amount' => 500,
                'date' => now(),
            ]);
        }

        $response = $this->actingAs($this->user)->get(route('budgets.index'));

        $incomeCategories = $response->viewData('incomeCategories');
        foreach ($excluded as $name) {
            $this->assertFalse($incomeCategories->contains('name', $name));
        }
    }

    #[Test]
    public function budget_index_only_shows_income_categories_with_transactions()
    {
        $withTransaction = Category::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'income',
            'name' => 'Freelance',
        ]);

        $withoutTransaction = Category::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'income',
            'name' => 'Rental Income',
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $withTransaction->id,
            'amount' => 800,
            'date' => now(),
        ]);

        $response = $this->actingAs($this->user)->get(route('budgets.index'));

        $incomeCategories = $response->viewData('incomeCategories');
        $this->assertTrue($incomeCategories->contains('id', $withTransaction->id));
        $this->assertFalse($incomeCategories->contains('id', $withoutTransaction->id));
    }

    #[Test]
    public function budget_index_only_shows_expense_categories_with_transactions()
    {
        $withTransaction = Category::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'expense',
            'name' => 'Groceries',
        ]);

        $withoutTransaction = Category::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'expense',
            'name' => 'Entertainment',
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $withTransaction->id,
            'amount' => 200,
            'date' => now(),
        ]);

        $response = $this->actingAs($this->user)->get(route('budgets.index'));

        $expenseCategories = $response->viewData('expenseCategories');
        $this->assertTrue($expenseCategories->contains('id', $withTransaction->id));
        $this->assertFalse($expenseCategories->contains('id', $withoutTransaction->id));
    }

    #[Test]
    public function budget_index_calculates_correct_yearly_totals_for_categories()
    {
        $category = Category::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'income',
            'name' => 'Salary',
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'amount' => 1000,
            'date' => now()->startOfYear(),
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'amount' => 2000,
            'date' => now()->startOfYear()->addMonths(1),
        ]);

        $response = $this->actingAs($this->user)->get(route('budgets.index'));

        $incomeCategories = $response->viewData('incomeCategories');
        $cat = $incomeCategories->firstWhere('id', $category->id);
        $this->assertEquals(3000, $cat->yearly_total);
    }

    #[Test]
    public function budget_index_calculates_budget_percentage_correctly()
    {
        $category = Category::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'expense',
            'name' => 'Rent',
        ]);

        Budget::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'year' => date('Y'),
            'month' => now()->month,
            'amount' => 1000,
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'amount' => 500,
            'date' => now(),
        ]);

        $response = $this->actingAs($this->user)->get(route('budgets.index'));

        $expenseCategories = $response->viewData('expenseCategories');
        $cat = $expenseCategories->firstWhere('id', $category->id);
        $this->assertEquals(50.0, $cat->budget_percentage);
    }

    #[Test]
    public function budget_index_excludes_client_fund_transactions_from_expenses()
    {
        $expenseCategory = Category::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'expense',
            'name' => 'Supplies',
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $expenseCategory->id,
            'amount' => 300,
            'date' => now(),
            'payment_method' => 'Client Fund',
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $expenseCategory->id,
            'amount' => 700,
            'date' => now(),
            'payment_method' => null,
        ]);

        $response = $this->actingAs($this->user)->get(route('budgets.index'));

        $expenseCategories = $response->viewData('expenseCategories');
        $cat = $expenseCategories->firstWhere('id', $expenseCategory->id);
        $this->assertEquals(700, $cat->yearly_total);
    }

    #[Test]
    public function budget_index_passes_required_variables_to_view()
    {
        $response = $this->actingAs($this->user)->get(route('budgets.index'));

        $response->assertViewHasAll([
            'incomeCategories',
            'expenseCategories',
            'budgets',
            'actuals',
            'year',
            'currentMonth',
            'loanStats',
            'savingsWithdrawals',
            'minYear',
            'maxYear',
            'accounts',
        ]);
    }

    #[Test]
    public function loan_stats_contain_correct_keys()
    {
        $response = $this->actingAs($this->user)->get(route('budgets.index'));

        $loanStats = $response->viewData('loanStats');
        $this->assertArrayHasKey('disbursed', $loanStats);
        $this->assertArrayHasKey('payments', $loanStats);
        $this->assertArrayHasKey('active_balance', $loanStats);
    }
}
