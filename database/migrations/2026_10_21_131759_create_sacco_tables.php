<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('saccos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->date('opening_date');
            $t->decimal('opening_shares', 14, 2)->default(0);
            $t->decimal('opening_deposits', 14, 2)->default(0);
            $t->decimal('opening_wallet', 14, 2)->default(0);
            $t->decimal('borrowing_multiple', 4, 1)->default(3);
            $t->timestamps();
            $t->unique('user_id'); // one sacco per user
        });

        Schema::create('sacco_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sacco_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('ledger');                 // shares | deposits | wallet
            $t->string('kind');                   // contribution | dividend | withdrawal | charges
            $t->date('period_date');
            $t->decimal('amount', 14, 2);         // signed: credit +, debit -
            $t->string('description')->nullable();
            $t->string('slot')->nullable();       // one-per-period guard, e.g. deposits:contribution:2026-09
            $t->timestamps();
            $t->unique(['sacco_id', 'slot']);     // NULL slots (withdrawals) are not restricted
        });

        Schema::create('sacco_loans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sacco_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->decimal('principal', 14, 2);      // for a loan already running: the current outstanding
            $t->decimal('annual_rate', 5, 2);     // reducing balance, % per year
            $t->unsignedSmallInteger('term_months');
            $t->decimal('instalment', 14, 2);
            $t->decimal('fee', 14, 2)->default(0);
            $t->decimal('amount_received', 14, 2)->nullable();
            $t->date('disbursed_date');
            $t->string('status')->default('active'); // active | cleared
            $t->unsignedBigInteger('refinanced_from_id')->nullable();
            $t->timestamps();
        });

        Schema::create('sacco_loan_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sacco_loan_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->date('period_date');              // 1st of the month paid
            $t->decimal('amount', 14, 2);
            $t->decimal('interest_portion', 14, 2)->default(0);
            $t->decimal('principal_portion', 14, 2)->default(0);
            $t->timestamps();
            $t->unique(['sacco_loan_id', 'period_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sacco_loan_payments');
        Schema::dropIfExists('sacco_loans');
        Schema::dropIfExists('sacco_entries');
        Schema::dropIfExists('saccos');
    }
};
