<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('retirement_funds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('type');                       // nssf | provident
            $t->decimal('opening_balance', 14, 2)->default(0);
            $t->date('opening_balance_date')->nullable();
            $t->text('notes')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('retirement_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('retirement_fund_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('entry_type');                 // contribution | interest
            $t->string('section');                    // tier1 | tier2 | main
            $t->date('period_date');                  // 1st of month, or 31 Dec for interest
            $t->decimal('employee_amount', 14, 2)->default(0);
            $t->decimal('employer_amount', 14, 2)->default(0);
            $t->decimal('voluntary_amount', 14, 2)->default(0);
            $t->decimal('interest_amount', 14, 2)->default(0);
            $t->text('notes')->nullable();
            $t->timestamps();

            $t->unique(['retirement_fund_id', 'entry_type', 'section', 'period_date'], 'ret_entry_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retirement_entries');
        Schema::dropIfExists('retirement_funds');
    }
};
