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
            $table->unsignedBigInteger('rolling_fund_id')->nullable()->after('account_id');

            $table->foreign('rolling_fund_id')
                ->references('id')
                ->on('rolling_funds')
                ->onDelete('cascade'); // Auto-delete transactions when rolling fund is deleted

            $table->index('rolling_fund_id'); // Add index for faster queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['rolling_fund_id']);
            $table->dropIndex(['rolling_fund_id']);
            $table->dropColumn('rolling_fund_id');
        });
    }
};
