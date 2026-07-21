<?php

namespace Tests\Unit\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use App\Services\TopUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopUpServiceTest extends TestCase
{
    use RefreshDatabase;

    private TopUpService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TopUpService::class);
        $this->user    = User::factory()->create();

        $this->actingAs($this->user);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeAccount(string $type): Account
    {
        return Account::factory()->create([
            'user_id'   => $this->user->id,
            'type'      => $type,
            'is_active' => true,
        ]);
    }

    private function makeChildCategory(string $name, string $type, ?string $parentName = null): Category
    {
        $parent = Category::factory()->create([
            'user_id'   => $this->user->id,
            'name'      => $parentName ?? ucfirst($type),
            'type'      => $type,
            'parent_id' => null,
            'is_active' => true,
        ]);

        return Category::factory()->create([
            'user_id'   => $this->user->id,
            'name'      => $name,
            'type'      => $type,
            'parent_id' => $parent->id,
            'is_active' => true,
        ]);
    }

    private function makeTopLevelCategory(string $name, string $type): Category
    {
        return Category::factory()->create([
            'user_id'   => $this->user->id,
            'name'      => $name,
            'type'      => $type,
            'parent_id' => null,
            'is_active' => true,
        ]);
    }

    // ── getCategories: mpesa ────────────────────────────────────────────────

    public function test_mpesa_includes_non_salary_income_and_liability_categories()
    {
        $this->makeChildCategory('Side Income', 'income');
        $this->makeChildCategory('Client Funds', 'liability');

        $categories = $this->service->getCategories('mpesa');
        $names = $categories->pluck('name')->all();

        $this->assertContains('Side Income', $names);
        $this->assertContains('Client Funds', $names);
    }

    public function test_mpesa_excludes_salary()
    {
        $this->makeChildCategory('Salary', 'income');

        $categories = $this->service->getCategories('mpesa');

        $this->assertNotContains('Salary', $categories->pluck('name')->all());
    }

    public function test_mpesa_excludes_reserved_system_categories()
    {
        $this->makeChildCategory('Loan Receipt', 'liability');
        $this->makeChildCategory('Balance Adjustment', 'income');

        $categories = $this->service->getCategories('mpesa');
        $names = $categories->pluck('name')->all();

        $this->assertNotContains('Loan Receipt', $names);
        $this->assertNotContains('Balance Adjustment', $names);
    }

    public function test_mpesa_excludes_expense_categories()
    {
        $this->makeChildCategory('Groceries', 'expense');

        $categories = $this->service->getCategories('mpesa');

        $this->assertNotContains('Groceries', $categories->pluck('name')->all());
    }

    public function test_top_level_categories_are_never_returned()
    {
        $this->makeTopLevelCategory('Income', 'income');
        $this->makeTopLevelCategory('Loans', 'liability');

        $categories = $this->service->getCategories('mpesa');
        $names = $categories->pluck('name')->all();

        $this->assertNotContains('Income', $names);
        $this->assertNotContains('Loans', $names);
    }

    // ── getCategories: bank ─────────────────────────────────────────────────

    public function test_bank_only_allows_salary_and_side_income()
    {
        $this->makeChildCategory('Salary', 'income');
        $this->makeChildCategory('Side Income', 'income');
        $this->makeChildCategory('Freelance', 'income');

        $categories = $this->service->getCategories('bank');
        $names = $categories->pluck('name')->all();

        $this->assertContains('Salary', $names);
        $this->assertContains('Side Income', $names);
        $this->assertNotContains('Freelance', $names);
    }

    public function test_bank_excludes_liability_categories()
    {
        $this->makeChildCategory('Client Funds', 'liability');

        $categories = $this->service->getCategories('bank');

        $this->assertNotContains('Client Funds', $categories->pluck('name')->all());
    }

    // ── getCategories: savings ──────────────────────────────────────────────

    public function test_savings_includes_all_income_categories_including_salary()
    {
        $this->makeChildCategory('Salary', 'income');
        $this->makeChildCategory('Side Income', 'income');

        $categories = $this->service->getCategories('savings');
        $names = $categories->pluck('name')->all();

        $this->assertContains('Salary', $names);
        $this->assertContains('Side Income', $names);
    }

    public function test_savings_excludes_liability_categories()
    {
        $this->makeChildCategory('Client Funds', 'liability');

        $categories = $this->service->getCategories('savings');

        $this->assertNotContains('Client Funds', $categories->pluck('name')->all());
    }

    // ── getCategories: airtel_money ─────────────────────────────────────────

    public function test_airtel_money_excludes_salary()
    {
        $this->makeChildCategory('Salary', 'income');
        $this->makeChildCategory('Side Income', 'income');

        $categories = $this->service->getCategories('airtel_money');
        $names = $categories->pluck('name')->all();

        $this->assertNotContains('Salary', $names);
        $this->assertContains('Side Income', $names);
    }

    public function test_airtel_money_excludes_liability_categories()
    {
        $this->makeChildCategory('Client Funds', 'liability');

        $categories = $this->service->getCategories('airtel_money');

        $this->assertNotContains('Client Funds', $categories->pluck('name')->all());
    }

    // ── getCategories: unknown / default account type ──────────────────────

    public function test_unknown_account_type_falls_back_to_income_and_liability()
    {
        $this->makeChildCategory('Salary', 'income');
        $this->makeChildCategory('Client Funds', 'liability');
        $this->makeChildCategory('Groceries', 'expense');

        $categories = $this->service->getCategories('cash');
        $names = $categories->pluck('name')->all();

        $this->assertContains('Salary', $names);
        $this->assertContains('Client Funds', $names);
        $this->assertNotContains('Groceries', $names);
    }

    // ── getCategories: user scoping ─────────────────────────────────────────

    public function test_categories_belonging_to_other_users_are_not_returned()
    {
        $otherUser = User::factory()->create();
        Category::factory()->create([
            'user_id'   => $otherUser->id,
            'name'      => 'Other Users Income',
            'type'      => 'income',
            'parent_id' => Category::factory()->create([
                'user_id' => $otherUser->id,
                'type'    => 'income',
            ])->id,
            'is_active' => true,
        ]);

        $categories = $this->service->getCategories('mpesa');

        $this->assertNotContains('Other Users Income', $categories->pluck('name')->all());
    }

    public function test_inactive_categories_are_not_returned()
    {
        $parent = Category::factory()->create([
            'user_id'   => $this->user->id,
            'name'      => 'Income',
            'type'      => 'income',
            'parent_id' => null,
            'is_active' => true,
        ]);

        Category::factory()->create([
            'user_id'   => $this->user->id,
            'name'      => 'Archived Category',
            'type'      => 'income',
            'parent_id' => $parent->id,
            'is_active' => false,
        ]);

        $categories = $this->service->getCategories('mpesa');

        $this->assertNotContains('Archived Category', $categories->pluck('name')->all());
    }

    // ── validateCategory ─────────────────────────────────────────────────────

    public function test_validate_category_rejects_category_belonging_to_another_user()
    {
        $account = $this->makeAccount('mpesa');
        $otherUser = User::factory()->create();
        $category = Category::factory()->create([
            'user_id'   => $otherUser->id,
            'name'      => 'Side Income',
            'type'      => 'income',
            'parent_id' => Category::factory()->create([
                'user_id' => $otherUser->id,
                'type'    => 'income',
            ])->id,
        ]);

        $error = $this->service->validateCategory($account, $category);

        $this->assertNotNull($error);
        $this->assertStringContainsString('valid category', $error);
    }

    public function test_validate_category_rejects_reserved_system_categories()
    {
        $account  = $this->makeAccount('mpesa');
        $category = $this->makeChildCategory('Loan Receipt', 'liability');

        $error = $this->service->validateCategory($account, $category);

        $this->assertNotNull($error);
        $this->assertStringContainsString('reserved', $error);
    }

    public function test_validate_category_rejects_disallowed_income_for_bank_accounts()
    {
        $account  = $this->makeAccount('bank');
        $category = $this->makeChildCategory('Freelance', 'income');

        $error = $this->service->validateCategory($account, $category);

        $this->assertNotNull($error);
        $this->assertStringContainsString('Salary', $error);
        $this->assertStringContainsString('Side Income', $error);
    }

    public function test_validate_category_allows_permitted_income_for_bank_accounts()
    {
        $account  = $this->makeAccount('bank');
        $category = $this->makeChildCategory('Salary', 'income');

        $error = $this->service->validateCategory($account, $category);

        $this->assertNull($error);
    }

    public function test_validate_category_allows_any_income_category_for_non_bank_accounts()
    {
        $account  = $this->makeAccount('mpesa');
        $category = $this->makeChildCategory('Freelance', 'income');

        $error = $this->service->validateCategory($account, $category);

        $this->assertNull($error);
    }

    public function test_validate_category_allows_liability_category_for_mpesa()
    {
        $account  = $this->makeAccount('mpesa');
        $category = $this->makeChildCategory('Client Funds', 'liability');

        $error = $this->service->validateCategory($account, $category);

        $this->assertNull($error);
    }
}
