<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referrer_float_reconciliations', function (Blueprint $table) {
            $table->unsignedBigInteger('transfer_id')->nullable()->change();

            $table->foreignId('referrer_payout_id')
                ->nullable()
                ->after('transfer_id')
                ->constrained('referrer_payouts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('referrer_float_reconciliations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referrer_payout_id');
            // transfer_id is left nullable on rollback; making it NOT NULL
            // again would fail if any payout-based rows exist.
        });
    }
};
