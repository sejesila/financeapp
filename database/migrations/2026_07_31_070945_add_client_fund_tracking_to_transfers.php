<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->foreignId('client_fund_id')->nullable()->after('is_client_fund')
                ->constrained('client_funds')->nullOnDelete();

            // Set when a webhook-driven transfer moves money out of an account
            // with outstanding client funds but can't be confidently auto-matched
            // to a specific fund — surfaces in the reconciliation command below.
            $table->boolean('needs_reconciliation')->default(false)->after('client_fund_id');
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_fund_id');
            $table->dropColumn('needs_reconciliation');
        });
    }
};
