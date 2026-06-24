<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ================================================================
        // STEP 1: Reassign Rolling Fund transactions to real categories
        // ================================================================

        $userIds = DB::table('transactions')
            ->whereIn('payment_method', ['Rolling Funds'])
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {

            // --- INCOME: Rolling Funds → Side Income ---
            $rollingIncome = DB::table('categories')
                ->where('user_id', $userId)
                ->where('name', 'Rolling Funds')
                ->where('type', 'income')
                ->first();

            if ($rollingIncome) {
                $sideIncome = DB::table('categories')
                    ->where('user_id', $userId)
                    ->where('name', 'Side Income')
                    ->where('type', 'income')
                    ->first();

                if (!$sideIncome) {
                    $sideIncomeId = DB::table('categories')->insertGetId([
                        'user_id'    => $userId,
                        'name'       => 'Side Income',
                        'type'       => 'income',
                        'is_active'  => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $sideIncomeId = $sideIncome->id;
                }

                DB::table('transactions')
                    ->where('category_id', $rollingIncome->id)
                    ->update([
                        'category_id'    => $sideIncomeId,
                        'payment_method' => null,
                        'description'    => DB::raw("REPLACE(description, 'Rolling Funds Returns', 'Side Income')"),
                    ]);

                DB::table('budgets')
                    ->where('category_id', $rollingIncome->id)
                    ->update(['category_id' => $sideIncomeId]);

                DB::table('categories')->where('id', $rollingIncome->id)->delete();
            }

            // --- EXPENSE: Rolling Funds → Other Expenses ---
            $rollingExpense = DB::table('categories')
                ->where('user_id', $userId)
                ->where('name', 'Rolling Funds')
                ->where('type', 'expense')
                ->first();

            if ($rollingExpense) {
                $otherExpenses = DB::table('categories')
                    ->where('user_id', $userId)
                    ->where('name', 'Other Expenses')
                    ->where('type', 'expense')
                    ->first();

                if (!$otherExpenses) {
                    $otherExpensesId = DB::table('categories')->insertGetId([
                        'user_id'    => $userId,
                        'name'       => 'Other Expenses',
                        'type'       => 'expense',
                        'is_active'  => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $otherExpensesId = $otherExpenses->id;
                }

                DB::table('transactions')
                    ->where('category_id', $rollingExpense->id)
                    ->update([
                        'category_id'    => $otherExpensesId,
                        'payment_method' => null,
                        'description'    => DB::raw("REPLACE(description, 'Rolling Funds Out', 'Other Expense')"),
                    ]);

                DB::table('budgets')
                    ->where('category_id', $rollingExpense->id)
                    ->update(['category_id' => $otherExpensesId]);

                DB::table('categories')->where('id', $rollingExpense->id)->delete();
            }
        }

        // ================================================================
        // STEP 2: Null out rolling_fund_id before dropping the tables
        // ================================================================
        DB::table('transactions')
            ->whereNotNull('rolling_fund_id')
            ->update(['rolling_fund_id' => null]);

        // STEP 3: Drop FK first, then index, then column
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['rolling_fund_id']);
            $table->dropIndex('transactions_rolling_fund_id_index');
            $table->dropColumn('rolling_fund_id');
        });

        // ================================================================
        // STEP 4: Drop the rolling fund tables
        // ================================================================
        Schema::dropIfExists('rolling_fund_limits');
        Schema::dropIfExists('rolling_funds');
    }

    public function down(): void
    {
        // Intentionally irreversible — restore from backup if needed
    }
};
