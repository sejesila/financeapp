<?php

namespace Tests\Unit\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TopUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TopUpServiceTest extends TestCase
{
    use RefreshDatabase;

    private TopUpService $service;
    private User $user;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TopUpService::class);
        $this->user    = User::factory()->create();
        $this->account = Account::factory()->create([
            'user_id'  => $this->user->id,
            'type'     => 'mpesa',
            'is_active' => true,
        ]);

        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // always reset fake time after each test

        parent::tearDown();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function seedSaccoCategory(): Category
    {
        $parent = Category::factory()->create([
            'user_id'   => $this->user->id,
            'name'      => 'Income',
            'type'      => 'income',
            'parent_id' => null,
            'is_active' => true,
        ]);

        return Category::factory()->create([
            'user_id'   => $this->user->id,
            'name'      => 'Sacco Dividends',
            'type'      => 'income',
            'parent_id' => $parent->id,
            'is_active' => true,
        ]);
    }

    private function recordSaccoTransaction(Category $sacco, string $date): void
    {
        Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'account_id'  => $this->account->id,
            'category_id' => $sacco->id,
            'date'        => $date,
        ]);
    }

    // ── Sacco Dividends window — getCategories visibility ─────────────────────

    public function test_shows_sacco_dividends_inside_the_window()
    {
        Carbon::setTestNow('2025-04-15');
        $this->seedSaccoCategory();

        [$categories] = $this->service->getCategories('mpesa');

        $this->assertContains('Sacco Dividends', $categories->pluck('name')->all());
    }

    public function test_hides_sacco_dividends_before_apr_10()
    {
        Carbon::setTestNow('2025-04-09');
        $this->seedSaccoCategory();

        [$categories] = $this->service->getCategories('mpesa');

        $this->assertNotContains('Sacco Dividends', $categories->pluck('name')->all());
    }

    public function test_hides_sacco_dividends_after_may_10()
    {
        Carbon::setTestNow('2025-05-11');
        $this->seedSaccoCategory();

        [$categories] = $this->service->getCategories('mpesa');

        $this->assertNotContains('Sacco Dividends', $categories->pluck('name')->all());
    }

    public function test_shows_sacco_dividends_on_apr_10_window_start_inclusive()
    {
        Carbon::setTestNow('2025-04-10');
        $this->seedSaccoCategory();

        [$categories] = $this->service->getCategories('mpesa');

        $this->assertContains('Sacco Dividends', $categories->pluck('name')->all());
    }

    public function test_shows_sacco_dividends_on_may_10_window_end_inclusive()
    {
        Carbon::setTestNow('2025-05-10');
        $this->seedSaccoCategory();

        [$categories] = $this->service->getCategories('mpesa');

        $this->assertContains('Sacco Dividends', $categories->pluck('name')->all());
    }

    public function test_hides_sacco_dividends_inside_window_when_already_recorded_this_year()
    {
        Carbon::setTestNow('2025-04-20');
        $sacco = $this->seedSaccoCategory();
        $this->recordSaccoTransaction($sacco, '2025-04-15');

        [$categories, $showSaccoDividends] = $this->service->getCategories('mpesa');

        $this->assertFalse($showSaccoDividends);
        $this->assertNotContains('Sacco Dividends', $categories->pluck('name')->all());
    }

    public function test_shows_sacco_dividends_when_only_prior_year_record_exists()
    {
        Carbon::setTestNow('2025-04-20');
        $sacco = $this->seedSaccoCategory();
        $this->recordSaccoTransaction($sacco, '2024-04-18'); // last year — must not block

        [$categories, $showSaccoDividends] = $this->service->getCategories('mpesa');

        $this->assertTrue($showSaccoDividends);
        $this->assertContains('Sacco Dividends', $categories->pluck('name')->all());
    }

    public function test_show_sacco_dividends_flag_is_false_outside_the_window()
    {
        Carbon::setTestNow('2025-06-01');
        $this->seedSaccoCategory();

        [, $showSaccoDividends] = $this->service->getCategories('mpesa');

        $this->assertFalse($showSaccoDividends);
    }

    // ── Sacco Dividends window — validateCategory enforcement ─────────────────

    public function test_allows_sacco_dividends_inside_window_when_not_yet_used()
    {
        Carbon::setTestNow('2025-04-20');
        $sacco = $this->seedSaccoCategory();

        $error = $this->service->validateCategory($this->account, $sacco);

        $this->assertNull($error);
    }

    public function test_blocks_sacco_dividends_outside_the_window()
    {
        Carbon::setTestNow('2025-06-01');
        $sacco = $this->seedSaccoCategory();

        $error = $this->service->validateCategory($this->account, $sacco);

        $this->assertNotNull($error);
        $this->assertStringContainsString('10 April', $error);
        $this->assertStringContainsString('10 May', $error);
    }

    public function test_blocks_sacco_dividends_when_already_recorded_this_year()
    {
        Carbon::setTestNow('2025-04-25');
        $sacco = $this->seedSaccoCategory();
        $this->recordSaccoTransaction($sacco, '2025-04-20');

        $error = $this->service->validateCategory($this->account, $sacco);

        $this->assertNotNull($error);
        $this->assertStringContainsString('already been recorded', $error);
    }

    public function test_soft_deleted_sacco_transaction_does_not_count_as_already_used()
    {
        Carbon::setTestNow('2025-04-25');
        $sacco = $this->seedSaccoCategory();

        $txn = Transaction::factory()->create([
            'user_id'     => $this->user->id,
            'account_id'  => $this->account->id,
            'category_id' => $sacco->id,
            'date'        => '2025-04-20',
        ]);
        $txn->delete(); // soft-delete — should not block re-recording

        $error = $this->service->validateCategory($this->account, $sacco);

        $this->assertNull($error);
    }
}
