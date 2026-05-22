<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Only drop columns if they exist
            $columns = ['reversed_by_transaction_id', 'reverses_transaction_id', 'reversal_reason', 'is_reversal', 'period_date'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Add columns back if they don't exist
            if (!Schema::hasColumn('transactions', 'reversed_by_transaction_id')) {
                $table->foreignId('reversed_by_transaction_id')
                    ->nullable()
                    ->constrained('transactions')
                    ->onDelete('set null')
                    ->comment('If this transaction was reversed, links to the reversal transaction');
            }

            if (!Schema::hasColumn('transactions', 'reverses_transaction_id')) {
                $table->foreignId('reverses_transaction_id')
                    ->nullable()
                    ->constrained('transactions')
                    ->onDelete('set null')
                    ->comment('If this is a reversal, links to the original transaction');
            }

            if (!Schema::hasColumn('transactions', 'reversal_reason')) {
                $table->text('reversal_reason')->nullable();
            }

            if (!Schema::hasColumn('transactions', 'is_reversal')) {
                $table->boolean('is_reversal')->default(false);
            }

            if (!Schema::hasColumn('transactions', 'period_date')) {
                $table->date('period_date')->nullable()->index();
            }
        });
    }
};
