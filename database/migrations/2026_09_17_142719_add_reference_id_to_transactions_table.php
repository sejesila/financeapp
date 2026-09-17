<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('reference_id')
                ->nullable()
                ->comment('Generic pointer to the row that generated this transaction, e.g. a loan_given_payments.id for a split-out interest transaction');

            $table->index('reference_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['reference_id']);
            $table->dropColumn('reference_id');
        });
    }
};
