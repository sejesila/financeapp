<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A single remittance from a referrer often covers several loans at
        // once with different amounts each — a plain FK column on loans_given
        // (like referrer_payout_id) can't represent that many-to-one split
        // cleanly, or handle a loan being topped up across multiple separate
        // remittances over time. This ledger row is one (loan, transfer, amount)
        // fact — "this much of this transfer was this loan's interest" — and
        // "pending" for a loan is just: interest recognized in the float for
        // that loan, minus the sum of these rows.
        Schema::create('referrer_float_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_given_id')->constrained('loans_given')->cascadeOnDelete();
            $table->foreignId('transfer_id')->constrained('transfers')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->index('loan_given_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrer_float_reconciliations');
    }
};
