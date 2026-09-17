<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans_given', function (Blueprint $table) {
            // The rate used for PROJECTIONS while the loan is still active —
            // seeded from the referrer's default_interest_rate at creation
            // time (or entered manually), and re-applied to a larger
            // principal on every overdue rollover. Deliberately separate
            // from interest_rate, which stays reserved for the FINAL,
            // realized rate closeAsRepaid() computes once, from what
            // actually came back.
            $table->decimal('expected_interest_rate', 5, 2)->nullable()->after('interest_rate');

            // What the borrower is currently expected to owe in interest on
            // top of principal_amount, at expected_interest_rate. Recomputed
            // on every overdue rollover against the new (larger) principal.
            $table->decimal('expected_interest_amount', 12, 2)->default(0)->after('interest_amount');

            // How many times this loan's overdue interest has been
            // capitalized into principal. Purely informational — lets the
            // loan page show "rolled over 2 times" rather than the fact
            // being invisible once due_date has moved on.
            $table->unsignedInteger('rollover_count')->default(0)->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('loans_given', function (Blueprint $table) {
            $table->dropColumn(['expected_interest_rate', 'expected_interest_amount', 'rollover_count']);
        });
    }
};
