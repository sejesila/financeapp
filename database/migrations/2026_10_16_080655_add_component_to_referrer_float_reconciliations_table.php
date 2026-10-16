<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referrer_float_reconciliations', function (Blueprint $table) {
            // 'interest' | 'principal'. Existing rows are all interest, since
            // that is the only thing a reconciliation could cover until now.
            $table->string('component', 10)->default('interest')->after('amount');
            $table->index(['loan_given_id', 'component']);
        });
    }

    public function down(): void
    {
        Schema::table('referrer_float_reconciliations', function (Blueprint $table) {
            $table->dropIndex(['loan_given_id', 'component']);
            $table->dropColumn('component');
        });
    }
};
