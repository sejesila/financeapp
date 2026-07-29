<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans_given', function (Blueprint $table) {
            $table->foreignId('referrer_id')->nullable()->after('borrower_contact')->constrained()->nullOnDelete();
            $table->decimal('referrer_share_percentage', 5, 2)->nullable()->after('referrer_id');
            $table->foreignId('referrer_payout_id')->nullable()->after('referrer_share_percentage')->constrained('referrer_payouts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('loans_given', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referrer_payout_id');
            $table->dropConstrainedForeignId('referrer_id');
            $table->dropColumn('referrer_share_percentage');
        });
    }
};
