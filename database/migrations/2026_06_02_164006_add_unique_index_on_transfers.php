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
        Schema::table('transfers', function (Blueprint $table) {
            $table->string('mpesa_reference')->nullable()->after('description');
            $table->unique(['user_id', 'mpesa_reference'], 'transfers_user_mpesa_ref_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropUnique('transfers_user_mpesa_ref_unique');
            $table->dropColumn('mpesa_reference');
        });
    }
};
