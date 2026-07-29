<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            // Mirrors is_client_fund: a transfer out of savings that's earmarked
            // for lending to someone else shouldn't count as personal "Savings Used"
            // in the Budgets page, same reasoning as client funds.
            $table->boolean('is_lending')->default(false)->after('is_client_fund');
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropColumn('is_lending');
        });
    }
};
