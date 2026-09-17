<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_given_payments', function (Blueprint $table) {
            // How much of this specific payment was recognized as interest at the
            // time it was recorded, vs. going toward principal. Defaults to 0 —
            // every existing payment predates this feature and was 100% principal
            // under the old accounting, which matches the loans_given backfill.
            $table->decimal('interest_portion', 12, 2)->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('loan_given_payments', function (Blueprint $table) {
            $table->dropColumn('interest_portion');
        });
    }
};
