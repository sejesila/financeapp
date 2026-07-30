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
        Schema::table('loans_given', function (Blueprint $table) {
            $table->boolean('referrer_deducted_before_deposit')->default(false)->after('referrer_share_percentage');
            $table->decimal('referrer_retained_amount', 12, 2)->nullable()->after('referrer_deducted_before_deposit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans_given', function (Blueprint $table) {
            //
        });
    }
};
