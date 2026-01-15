<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_money_type_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('account_type', ['mpesa', 'airtel_money']);
            $table->enum('transaction_type', ['send_money', 'paybill', 'buy_goods', 'pochi_la_biashara']);
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();

            // Ensure one record per user/account_type/transaction_type combination
            // Shortened name to avoid MySQL 64 character limit
            $table->unique(['user_id', 'account_type', 'transaction_type'], 'mm_type_usage_unique');
            $table->index(['user_id', 'account_type', 'usage_count'], 'mm_type_usage_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_money_type_usage');
    }
};
