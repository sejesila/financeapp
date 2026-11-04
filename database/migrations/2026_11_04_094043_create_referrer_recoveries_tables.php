<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A write-off charged to a referrer, recovered from her commissions.
        Schema::create('referrer_recoveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('referrer_id')->constrained();
            $t->foreignId('loan_given_id')->unique()->constrained('loans_given');
            $t->decimal('amount', 12, 2);                  // total charged to the referrer
            $t->decimal('recovered', 12, 2)->default(0);   // recovered so far
            $t->string('status')->default('open');         // open | settled
            $t->timestamps();

            $t->index(['referrer_id', 'status']);
        });

        // Which payout absorbed how much of which recovery.
        Schema::create('referrer_recovery_applications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('referrer_recovery_id')->constrained('referrer_recoveries');
            $t->foreignId('referrer_payout_id')->constrained('referrer_payouts');
            $t->decimal('amount', 12, 2);
            $t->timestamps();
        });

        Schema::table('referrer_payouts', function (Blueprint $t) {
            $t->decimal('gross_commission', 12, 2)->default(0)->after('share_percentage');
            $t->decimal('recovery_deducted', 12, 2)->default(0)->after('gross_commission');
        });

        // A payout fully absorbed by a recovery moves no cash, so no transaction.
        Schema::table('referrer_payouts', function (Blueprint $t) {
            $t->unsignedBigInteger('transaction_id')->nullable()->change();
        });

        // Backfill so historical payouts show gross = amount paid.
        \Illuminate\Support\Facades\DB::table('referrer_payouts')
            ->update(['gross_commission' => \Illuminate\Support\Facades\DB::raw('amount_paid')]);
    }

    public function down(): void
    {
        Schema::dropIfExists('referrer_recovery_applications');
        Schema::dropIfExists('referrer_recoveries');

        Schema::table('referrer_payouts', function (Blueprint $t) {
            $t->dropColumn(['gross_commission', 'recovery_deducted']);
        });

        // transaction_id is left nullable on rollback: restoring NOT NULL would
        // fail if any fully-absorbed payouts (null transaction) exist.
    }
};
