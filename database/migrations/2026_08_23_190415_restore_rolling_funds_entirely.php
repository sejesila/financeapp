<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rebuilds the schema that was dropped by
     * 2026_06_24_194110_remove_rolling_funds_entirely.php
     *
     * Note: that migration's down() was intentionally left empty, and it
     * reassigned existing "Rolling Funds" transactions to "Side Income" /
     * "Other Expenses" categories before dropping the tables. This migration
     * only restores the STRUCTURE (tables/columns) — it does not and cannot
     * recover the original transaction-category linkage or any rows that
     * existed in rolling_funds / rolling_fund_limits before the drop, unless
     * you have a database backup from before that migration ran.
     */
    public function up(): void
    {
        Schema::create('rolling_funds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('stake_amount', 12, 2);
            $table->decimal('winnings', 12, 2)->nullable();
            $table->string('status')->default('pending'); // pending | completed
            $table->date('completed_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'date']);
        });

        Schema::create('rolling_fund_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('monthly_stake_limit', 12, 2)->nullable();
            $table->decimal('single_stake_limit', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('rolling_fund_id')
                ->nullable()
                ->after('id')
                ->constrained('rolling_funds')
                ->nullOnDelete();

            $table->index('rolling_fund_id', 'transactions_rolling_fund_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['rolling_fund_id']);
            $table->dropIndex('transactions_rolling_fund_id_index');
            $table->dropColumn('rolling_fund_id');
        });

        Schema::dropIfExists('rolling_fund_limits');
        Schema::dropIfExists('rolling_funds');
    }
};
