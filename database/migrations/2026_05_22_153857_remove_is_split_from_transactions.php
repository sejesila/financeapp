<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop foreign keys outside Schema closure so exceptions can be caught
        foreach (['transactions_reversed_by_transaction_id_foreign', 'transactions_reverses_transaction_id_foreign'] as $fk) {
            try {
                DB::statement("ALTER TABLE transactions DROP FOREIGN KEY {$fk}");
            } catch (\Exception $e) {}
        }

        // Now drop columns safely
        Schema::table('transactions', function (Blueprint $table) {
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
            if (!Schema::hasColumn('transactions', 'reversed_by_transaction_id')) {
                $table->foreignId('reversed_by_transaction_id')->nullable()->constrained('transactions')->onDelete('set null');
            }
            if (!Schema::hasColumn('transactions', 'reverses_transaction_id')) {
                $table->foreignId('reverses_transaction_id')->nullable()->constrained('transactions')->onDelete('set null');
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
