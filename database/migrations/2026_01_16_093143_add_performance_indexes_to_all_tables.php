<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Composite index for balance calculations (most critical)
            // Used in: updateBalance() method - account + category type queries
            $table->index(['account_id', 'category_id', 'deleted_at'], 'idx_account_category_deleted');

            // Date-based queries (for reports and filtering)
            // Used in: account statements, date range filters
            $table->index(['account_id', 'date'], 'idx_account_date');

            // User-specific queries
            // Used in: user dashboard, transaction lists
            $table->index(['user_id', 'date'], 'idx_user_date');

            // Amount-based queries (for analytics)
            // Used in: large transaction queries, sum calculations
            $table->index(['account_id', 'amount'], 'idx_account_amount');

            // Period date for monthly/yearly reports
            // Used in: period-based reporting
            $table->index(['account_id', 'period_date'], 'idx_account_period');

            // Category-based filtering
            // Used in: category expense/income reports
            $table->index(['category_id', 'date'], 'idx_category_date');

            // Soft delete queries (already indexed via deleted_at but adding composite)
            // Used in: active transaction queries
            $table->index(['user_id', 'deleted_at'], 'idx_user_deleted');
        });

        Schema::table('transfers', function (Blueprint $table) {
            // From account transfers (critical for balance calculation)
            // Used in: updateBalance() - transfers_out calculation
            $table->index('from_account_id', 'idx_from_account');

            // To account transfers (critical for balance calculation)
            // Used in: updateBalance() - transfers_in calculation
            $table->index('to_account_id', 'idx_to_account');

            // Date-based transfer queries
            $table->index(['from_account_id', 'date'], 'idx_from_account_date');
            $table->index(['to_account_id', 'date'], 'idx_to_account_date');

            // User transfer history
            $table->index(['user_id', 'date'], 'idx_user_transfer_date');
        });

        Schema::table('categories', function (Blueprint $table) {
            // Type and name lookup (critical for balance calculation)
            // Used in: updateBalance() - category type filtering
            $table->index(['type', 'name'], 'idx_type_name');

            // User categories
            // Used in: category lists, filtering
            $table->index(['user_id', 'type'], 'idx_user_type');

            // Active categories
            $table->index(['user_id', 'is_active'], 'idx_user_active');

            // Parent-child relationships
            $table->index('parent_id', 'idx_parent');
        });

        Schema::table('accounts', function (Blueprint $table) {
            // User active accounts
            // Used in: account lists, dropdowns
            $table->index(['user_id', 'is_active'], 'idx_user_active_accounts');

            // Account type filtering
            $table->index(['user_id', 'type'], 'idx_user_type_accounts');

            // Balance queries
            $table->index('current_balance', 'idx_current_balance');
        });

        Schema::table('loans', function (Blueprint $table) {
            // User loan queries
            $table->index(['user_id', 'status'], 'idx_user_status');

            // Date-based loan queries
            $table->index(['user_id', 'disbursed_date'], 'idx_user_disbursed');
            $table->index(['user_id', 'due_date'], 'idx_user_due');

            // Account-based loan lookup
            $table->index('account_id', 'idx_account_loans');

            // Loan type filtering
            $table->index(['user_id', 'loan_type'], 'idx_user_loan_type');
        });

        Schema::table('loan_payments', function (Blueprint $table) {
            // Loan payment history
            $table->index(['loan_id', 'payment_date'], 'idx_loan_payment_date');

            // Account payment tracking
            $table->index('account_id', 'idx_account_payments');

            // User payment history
            $table->index(['user_id', 'payment_date'], 'idx_user_payment_date');
        });

        Schema::table('client_funds', function (Blueprint $table) {
            // User client funds
            $table->index(['user_id', 'status'], 'idx_user_cf_status');

            // Date-based queries
            $table->index(['user_id', 'received_date'], 'idx_user_cf_received');

            // Account-based lookup
            $table->index('account_id', 'idx_account_cf');

            // Type filtering
            $table->index(['user_id', 'type'], 'idx_user_cf_type');
        });

        Schema::table('client_fund_transactions', function (Blueprint $table) {
            // Client fund transaction history
            $table->index(['client_fund_id', 'type'], 'idx_cf_type');
            $table->index(['client_fund_id', 'date'], 'idx_cf_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_account_category_deleted');
            $table->dropIndex('idx_account_date');
            $table->dropIndex('idx_user_date');
            $table->dropIndex('idx_account_amount');
            $table->dropIndex('idx_account_period');
            $table->dropIndex('idx_category_date');
            $table->dropIndex('idx_user_deleted');
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->dropIndex('idx_from_account');
            $table->dropIndex('idx_to_account');
            $table->dropIndex('idx_from_account_date');
            $table->dropIndex('idx_to_account_date');
            $table->dropIndex('idx_user_transfer_date');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('idx_type_name');
            $table->dropIndex('idx_user_type');
            $table->dropIndex('idx_user_active');
            $table->dropIndex('idx_parent');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex('idx_user_active_accounts');
            $table->dropIndex('idx_user_type_accounts');
            $table->dropIndex('idx_current_balance');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndex('idx_user_status');
            $table->dropIndex('idx_user_disbursed');
            $table->dropIndex('idx_user_due');
            $table->dropIndex('idx_account_loans');
            $table->dropIndex('idx_user_loan_type');
        });

        Schema::table('loan_payments', function (Blueprint $table) {
            $table->dropIndex('idx_loan_payment_date');
            $table->dropIndex('idx_account_payments');
            $table->dropIndex('idx_user_payment_date');
        });

        Schema::table('client_funds', function (Blueprint $table) {
            $table->dropIndex('idx_user_cf_status');
            $table->dropIndex('idx_user_cf_received');
            $table->dropIndex('idx_account_cf');
            $table->dropIndex('idx_user_cf_type');
        });

        Schema::table('client_fund_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_cf_type');
            $table->dropIndex('idx_cf_date');
        });
    }
};
