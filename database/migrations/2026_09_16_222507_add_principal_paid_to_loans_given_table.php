<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans_given', function (Blueprint $table) {
            // Tracks principal repaid specifically, separate from amount_paid
            // (which is the lifetime total of everything received, principal
            // and interest combined). remaining_principal / balance are derived
            // from this, not from amount_paid, once interest starts being split
            // out of individual rollover payments.
            $table->decimal('principal_paid', 12, 2)->default(0)->after('amount_paid');
        });

        // Backfill: before this feature, no payment ever had its interest split
        // out — every shilling received was treated as reducing principal. So
        // setting principal_paid = amount_paid for existing rows preserves every
        // current loan's remaining_principal / balance exactly as it is today.
        DB::table('loans_given')->update(['principal_paid' => DB::raw('amount_paid')]);
    }

    public function down(): void
    {
        Schema::table('loans_given', function (Blueprint $table) {
            $table->dropColumn('principal_paid');
        });
    }
};
