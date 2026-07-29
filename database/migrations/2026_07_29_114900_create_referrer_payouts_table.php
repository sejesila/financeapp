<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrer_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('referrer_id')->constrained()->onDelete('cascade');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('total_interest', 10, 2);      // sum of interest across included loans
            $table->decimal('share_percentage', 5, 2);      // % applied for this payout
            $table->decimal('amount_paid', 10, 2);          // total_interest * share_percentage
            $table->foreignId('account_id')->constrained(); // account the payout was paid from
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->date('paid_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrer_payouts');
    }
};
