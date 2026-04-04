<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_splits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transaction_id')
                ->constrained()
                ->onDelete('cascade')
                ->comment('The parent transaction this split belongs to');

            $table->foreignId('account_id')
                ->constrained()
                ->onDelete('cascade')
                ->comment('The account used for this portion of the payment');

            $table->decimal('amount', 10, 2)
                ->comment('Amount paid from this account');

            $table->string('payment_method')->nullable()
                ->comment('Derived from account type: Cash, Mpesa, Airtel Money, etc.');

            $table->string('mobile_money_type')->nullable()
                ->index()
                ->comment('send_money, paybill, buy_goods, pochi_la_biashara — only for mobile money accounts');

            // Fee tracking per split (mirrors transactions table pattern)
            $table->foreignId('related_fee_transaction_id')
                ->nullable()
                ->constrained('transactions')
                ->onDelete('set null')
                ->comment('Links to the fee transaction generated for this split, if any');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_splits');
    }
};
