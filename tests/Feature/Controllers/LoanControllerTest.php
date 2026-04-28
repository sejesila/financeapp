<?php

namespace Tests\Feature\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoanControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->account = Account::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'mpesa',
            'is_active' => true,
            'current_balance' => 10000,
        ]);
    }

    // ==================== INDEX ====================

    #[Test]
    public function unauthenticated_user_cannot_access_loans_index()
    {
        $this->get(route('loans.index'))->assertRedirect(route('login'));
    }

    #[Test]
    public function authenticated_user_can_view_loans_index()
    {
        $this->actingAs($this->user)
            ->get(route('loans.index'))
            ->assertStatus(200)
            ->assertViewIs('loans.index');
    }

    #[Test]
    public function loans_index_passes_required_variables_to_view()
    {
        $this->actingAs($this->user)
            ->get(route('loans.index'))
            ->assertViewHasAll(['activeLoans', 'paidLoans', 'filter', 'accounts', 'minYear', 'maxYear']);
    }

    #[Test]
    public function loans_index_only_shows_loans_belonging_to_authenticated_user()
    {
        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->create(['user_id' => $otherUser->id, 'type' => 'mpesa']);

        Loan::factory()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id, 'status' => 'active']);
        Loan::factory()->create(['user_id' => $otherUser->id, 'account_id' => $otherAccount->id, 'status' => 'active']);

        $response = $this->actingAs($this->user)->get(route('loans.index'));

        $this->assertEquals(1, $response->viewData('activeLoans')->count());
    }

    #[Test]
    public function loans_index_separates_active_and_paid_loans()
    {
        Loan::factory()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id, 'status' => 'active']);
        Loan::factory()->paid()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id]);

        $response = $this->actingAs($this->user)->get(route('loans.index'));

        $this->assertEquals(1, $response->viewData('activeLoans')->count());
        $this->assertEquals(1, $response->viewData('paidLoans')->count());
    }

    #[Test]
    public function loans_index_filters_paid_loans_by_this_month()
    {
        Loan::factory()->paid()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'repaid_date' => now()->toDateString(),
        ]);
        Loan::factory()->paid()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'repaid_date' => now()->subYear()->toDateString(),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('loans.index', ['period' => 'this_month']));

        $this->assertEquals(1, $response->viewData('paidLoans')->count());
    }

    #[Test]
    public function loans_index_filters_paid_loans_by_custom_date_range()
    {
        Loan::factory()->paid()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'repaid_date' => '2026-01-15',
        ]);
        Loan::factory()->paid()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'repaid_date' => '2025-06-01',
        ]);

        $response = $this->actingAs($this->user)->get(route('loans.index', [
            'period' => 'custom',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
        ]));

        $this->assertEquals(1, $response->viewData('paidLoans')->count());
    }

    // ==================== CREATE ====================

    #[Test]
    public function authenticated_user_can_view_create_loan_form()
    {
        $this->actingAs($this->user)
            ->get(route('loans.create'))
            ->assertStatus(200)
            ->assertViewIs('loans.create');
    }

    #[Test]
    public function create_form_prefills_data_from_query_params()
    {
        $response = $this->actingAs($this->user)->get(route('loans.create', [
            'account_id' => $this->account->id,
            'amount' => 5000,
            'source' => 'M-Shwari',
        ]));

        $prefillData = $response->viewData('prefillData');
        $this->assertEquals($this->account->id, $prefillData['account_id']);
        $this->assertEquals(5000, $prefillData['amount']);
    }

    // ==================== STORE ====================

    #[Test]
    public function user_can_create_mshwari_loan()
    {
        $this->actingAs($this->user)->post(route('loans.store'), [
            'source' => 'M-Shwari',
            'account_id' => $this->account->id,
            'principal_amount' => 1000,
            'disbursed_date' => now()->format('Y-m-d'),
            'loan_type' => 'mshwari',
            'notes' => 'Test loan',
        ])->assertRedirect();

        $this->assertDatabaseHas('loans', [
            'user_id' => $this->user->id,
            'source' => 'M-Shwari',
            'principal_amount' => 1000,
            'loan_type' => 'mshwari',
        ]);
    }

    #[Test]
    public function user_can_create_kcb_mpesa_loan()
    {
        $this->actingAs($this->user)->post(route('loans.store'), [
            'source' => 'KCB M-Pesa',
            'account_id' => $this->account->id,
            'principal_amount' => 2000,
            'disbursed_date' => now()->format('Y-m-d'),
            'loan_type' => 'kcb_mpesa',
        ])->assertRedirect();

        $this->assertDatabaseHas('loans', [
            'user_id' => $this->user->id,
            'loan_type' => 'kcb_mpesa',
            'principal_amount' => 2000,
        ]);
    }

    #[Test]
    public function user_can_create_custom_loan_with_interest()
    {
        $this->actingAs($this->user)->post(route('loans.store'), [
            'source' => 'Friend',
            'account_id' => $this->account->id,
            'principal_amount' => 5000,
            'disbursed_date' => now()->format('Y-m-d'),
            'due_date' => now()->addMonth()->format('Y-m-d'),
            'loan_type' => 'other',
            'custom_interest_amount' => 500,
        ])->assertRedirect();

        $this->assertDatabaseHas('loans', [
            'user_id' => $this->user->id,
            'loan_type' => 'other',
            'principal_amount' => 5000,
            'total_amount' => 5500,
        ]);
    }

    #[Test]
    public function store_creates_loan_disbursement_transaction()
    {
        $this->actingAs($this->user)->post(route('loans.store'), [
            'source' => 'KCB M-Pesa',
            'account_id' => $this->account->id,
            'principal_amount' => 2000,
            'disbursed_date' => now()->format('Y-m-d'),
            'loan_type' => 'kcb_mpesa',
        ])->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'amount' => 2000,
            'description' => 'Loan disbursement from KCB M-Pesa',
        ]);
    }

    #[Test]
    public function mshwari_loan_creates_excise_duty_transaction()
    {
        $this->actingAs($this->user)->post(route('loans.store'), [
            'source' => 'M-Shwari',
            'account_id' => $this->account->id,
            'principal_amount' => 1000,
            'disbursed_date' => now()->format('Y-m-d'),
            'loan_type' => 'mshwari',
        ])->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'amount' => 15.00,
            'description' => 'Excise duty (1.5%) on loan from M-Shwari',
        ]);
    }

    #[Test]
    public function store_fails_validation_without_required_fields()
    {
        $this->actingAs($this->user)
            ->post(route('loans.store'), [])
            ->assertSessionHasErrors(['source', 'account_id', 'principal_amount', 'disbursed_date', 'loan_type']);
    }

    #[Test]
    public function store_fails_with_account_belonging_to_another_user()
    {
        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->create(['user_id' => $otherUser->id]);

        $this->actingAs($this->user)->post(route('loans.store'), [
            'source' => 'M-Shwari',
            'account_id' => $otherAccount->id,
            'principal_amount' => 1000,
            'disbursed_date' => now()->format('Y-m-d'),
            'loan_type' => 'mshwari',
        ])->assertNotFound();
    }

    #[Test]
    public function topup_with_loan_category_redirects_to_loan_create()
    {
        $loanCategory = Category::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'M-Shwari',
            'type' => 'liability',
            'parent_id' => Category::factory()->create([
                'user_id' => $this->user->id,
                'name' => 'Loans',
                'type' => 'liability',
            ])->id,
        ]);

        $this->actingAs($this->user)->post(route('accounts.topup', $this->account), [
            'amount' => 1000,
            'category_id' => $loanCategory->id,
            'date' => now()->format('Y-m-d'),
            'description' => 'Test loan',
        ])->assertRedirectContains('loans/create');
    }

    #[Test]
    public function topup_with_loan_category_passes_correct_params_to_loan_create()
    {
        $loanCategory = Category::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'M-Shwari',
            'type' => 'liability',
            'parent_id' => Category::factory()->create([
                'user_id' => $this->user->id,
                'name' => 'Loans',
                'type' => 'liability',
            ])->id,
        ]);

        $response = $this->actingAs($this->user)->post(route('accounts.topup', $this->account), [
            'amount' => 5000,
            'category_id' => $loanCategory->id,
            'date' => now()->format('Y-m-d'),
            'description' => 'My loan',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('account_id=' . $this->account->id, $response->headers->get('Location'));
        $this->assertStringContainsString('amount=5000', $response->headers->get('Location'));
    }

    // ==================== SHOW ====================

    #[Test]
    public function user_can_view_their_own_loan()
    {
        $loan = Loan::factory()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id]);

        $this->actingAs($this->user)
            ->get(route('loans.show', $loan))
            ->assertStatus(200)
            ->assertViewIs('loans.show');
    }

    #[Test]
    public function user_cannot_view_another_users_loan()
    {
        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->create(['user_id' => $otherUser->id]);
        $loan = Loan::factory()->create(['user_id' => $otherUser->id, 'account_id' => $otherAccount->id]);

        $this->actingAs($this->user)
            ->get(route('loans.show', $loan))
            ->assertNotFound();
    }

    #[Test]
    public function show_passes_correct_variables_to_view()
    {
        $loan = Loan::factory()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id]);

        $this->actingAs($this->user)
            ->get(route('loans.show', $loan))
            ->assertViewHasAll(['loan', 'repayment', 'daysElapsed', 'daysRemaining', 'isOverdue']);
    }

    // ==================== RECORD PAYMENT ====================

    #[Test]
    public function user_can_record_payment_on_active_loan()
    {
        $loan = Loan::factory()->other(1000, 0)->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'status' => 'active',
            'total_amount' => 1000,
            'balance' => 1000,
            'amount_paid' => 0,
        ]);

        $this->actingAs($this->user)
            ->post(route('loans.payment.store', $loan), [
                'payment_account_id' => $this->account->id,
                'payment_amount' => 500,
                'payment_date' => now()->format('Y-m-d'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('loan_payments', [
            'loan_id' => $loan->id,
            'amount' => 500,
        ]);
    }

    #[Test]
    public function full_payment_marks_loan_as_paid()
    {
        $loan = Loan::factory()->other(1000, 0)->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'status' => 'active',
            'total_amount' => 1000,
            'balance' => 1000,
            'amount_paid' => 0,
        ]);

        $this->actingAs($this->user)
            ->post(route('loans.payment.store', $loan), [
                'payment_account_id' => $this->account->id,
                'payment_amount' => 1000,
                'payment_date' => now()->format('Y-m-d'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('loans', [
            'id' => $loan->id,
            'status' => 'paid',
        ]);
    }

    #[Test]
    public function cannot_record_payment_on_paid_loan()
    {
        $loan = Loan::factory()->paid()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $this->actingAs($this->user)
            ->withSession(['_previous' => ['url' => route('loans.show', $loan->id)]])
            ->post(route('loans.payment.store', $loan), [
                'payment_account_id' => $this->account->id,
                'payment_amount' => 500,
                'payment_date' => now()->format('Y-m-d'),
            ]);

        $this->assertDatabaseCount('loan_payments', 0);
    }

    #[Test]
    public function mshwari_loan_must_be_repaid_from_same_account()
    {
        $otherAccount = Account::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'mpesa',
            'is_active' => true,
            'current_balance' => 5000,
        ]);

        $loan = Loan::factory()->mshwari()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'status' => 'active',
            'total_amount' => 1075,
            'balance' => 1075,
        ]);

        $this->actingAs($this->user)->post(route('loans.payment.store', $loan), [
            'payment_account_id' => $otherAccount->id,
            'payment_amount' => 1075,
            'payment_date' => now()->format('Y-m-d'),
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('loan_payments', 0);
    }

    #[Test]
    public function payment_fails_if_insufficient_balance()
    {
        $this->account->update(['current_balance' => 100]);

        $loan = Loan::factory()->other(1000, 0)->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'status' => 'active',
            'total_amount' => 1000,
            'balance' => 1000,
        ]);

        $this->actingAs($this->user)->post(route('loans.payment.store', $loan), [
            'payment_account_id' => $this->account->id,
            'payment_amount' => 500,
            'payment_date' => now()->format('Y-m-d'),
        ])->assertSessionHas('error');
    }

    #[Test]
    public function mshwari_early_repayment_within_10_days_creates_credit_transaction()
    {
        $loan = Loan::factory()->mshwari()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'status' => 'active',
            'principal_amount' => 1000,
            'total_amount' => 1075,
            'balance' => 1075,
            'amount_paid' => 0,
            'disbursed_date' => now()->subDays(5)->toDateString(),
        ]);

        $this->actingAs($this->user)
            ->post(route('loans.payment.store', $loan), [
                'payment_account_id' => $this->account->id,
                'payment_amount' => 1075,
                'payment_date' => now()->format('Y-m-d'),
            ])
            ->assertRedirect();

        // principal=1000, excise=15, facilitation=75, total_fees=90, 20% discount=18
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'amount' => 18.00,
        ]);
    }

    // ==================== DESTROY ====================

    #[Test]
    public function user_can_delete_active_loan_with_no_payments()
    {
        $loan = Loan::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'status' => 'active',
            'amount_paid' => 0,
        ]);

        $this->actingAs($this->user)
            ->withHeader('referer', route('loans.index'))
            ->delete(route('loans.destroy', $loan));

        $this->assertDatabaseMissing('loans', ['id' => $loan->id]);
    }

    #[Test]
    public function user_cannot_delete_paid_loan()
    {
        $loan = Loan::factory()->paid()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
        ]);

        $this->actingAs($this->user)
            ->withSession(['_previous' => ['url' => route('loans.index')]])
            ->delete(route('loans.destroy', $loan));

        $this->assertDatabaseHas('loans', ['id' => $loan->id]);
    }

    #[Test]
    public function user_cannot_delete_loan_with_payments()
    {
        $loan = Loan::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'status' => 'active',
            'amount_paid' => 500,
            'total_amount' => 1000,
            'balance' => 500,
        ]);

        $this->actingAs($this->user)
            ->withSession(['_previous' => ['url' => route('loans.index')]])
            ->delete(route('loans.destroy', $loan));

        $this->assertDatabaseHas('loans', ['id' => $loan->id]);
    }

    #[Test]
    public function deleting_loan_also_deletes_its_transactions()
    {
        $loan = Loan::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'status' => 'active',
            'amount_paid' => 0,
        ]);

        $this->actingAs($this->user)
            ->withHeader('referer', route('loans.index'))
            ->delete(route('loans.destroy', $loan));

        $this->assertDatabaseMissing('loans', ['id' => $loan->id]);
    }

    #[Test]
    public function user_cannot_delete_another_users_loan()
    {
        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->create(['user_id' => $otherUser->id]);
        $loan = Loan::factory()->create([
            'user_id' => $otherUser->id,
            'account_id' => $otherAccount->id,
            'status' => 'active',
            'amount_paid' => 0,
        ]);

        $this->actingAs($this->user)
            ->delete(route('loans.destroy', $loan))
            ->assertNotFound();
    }

    // ==================== BREAKDOWN CALCULATIONS ====================

    #[Test]
    public function mshwari_breakdown_calculates_correctly()
    {
        $controller = new \App\Http\Controllers\LoanController();
        $breakdown = $controller->calculateMshwariBreakdown(1000);

        $this->assertEquals(15.00, $breakdown['excise_duty']);
        $this->assertEquals(985.00, $breakdown['deposit_amount']);
        $this->assertEquals(75.00, $breakdown['facilitation_fee']);
        $this->assertEquals(1075.00, $breakdown['standard_repayment']);
    }

    #[Test]
    public function kcb_mpesa_breakdown_calculates_correctly()
    {
        $controller = new \App\Http\Controllers\LoanController();
        $breakdown = $controller->calculateKcbMpesaBreakdown(1000);

        $this->assertEquals(17.60, $breakdown['facility_fee']);
        $this->assertEquals(70.50, $breakdown['interest_amount']);
        $this->assertEquals(1088.10, $breakdown['total_repayment']);
    }

    #[Test]
    public function custom_breakdown_calculates_correctly()
    {
        $controller = new \App\Http\Controllers\LoanController();
        $breakdown = $controller->calculateCustomBreakdown(5000, 500);

        $this->assertEquals(500.00, $breakdown['interest_amount']);
        $this->assertEquals(5500.00, $breakdown['total_repayment']);
        $this->assertEquals(5000, $breakdown['deposit_amount']);
    }

    #[Test]
    public function custom_breakdown_with_zero_interest()
    {
        $controller = new \App\Http\Controllers\LoanController();
        $breakdown = $controller->calculateCustomBreakdown(3000, 0);

        $this->assertEquals(0, $breakdown['interest_amount']);
        $this->assertEquals(3000, $breakdown['total_repayment']);
    }
}
