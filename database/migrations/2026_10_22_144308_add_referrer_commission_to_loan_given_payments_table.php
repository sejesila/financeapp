<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_given_payments', function (Blueprint $t) {
            $t->decimal('referrer_share_percentage', 5, 2)->nullable();
            $t->decimal('referrer_commission', 12, 2)->default(0);
            $t->boolean('referrer_commission_kept')->default(false);
            $t->foreignId('referrer_payout_id')->nullable()->constrained('referrer_payouts')->nullOnDelete();
        });

        // Backfill: existing payments inherit from their loan.
        DB::table('loans_given')->whereNotNull('referrer_id')->orderBy('id')->each(function ($loan) {
            $share = (float) ($loan->referrer_share_percentage
                ?? DB::table('referrers')->where('id', $loan->referrer_id)->value('default_share_percentage') ?? 0);
            $kept  = (bool) $loan->referrer_deducted_before_deposit;

            foreach (DB::table('loan_given_payments')->where('loan_given_id', $loan->id)->where('interest_portion', '>', 0)->get() as $p) {
                $ip  = (float) $p->interest_portion;
                $com = ($kept && $share < 100) ? round($ip * $share / (100 - $share), 2) : round($ip * $share / 100, 2);

                DB::table('loan_given_payments')->where('id', $p->id)->update([
                    'referrer_share_percentage' => $share,
                    'referrer_commission'       => $com,
                    'referrer_commission_kept'  => $kept,
                    'referrer_payout_id'        => $kept ? null : $loan->referrer_payout_id,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_given_payments', function (Blueprint $t) {
            $t->dropConstrainedForeignId('referrer_payout_id');
            $t->dropColumn(['referrer_share_percentage', 'referrer_commission', 'referrer_commission_kept']);
        });
    }
};
