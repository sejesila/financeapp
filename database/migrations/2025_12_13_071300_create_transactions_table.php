<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->string('payment_method')->nullable();
            $table->string('mobile_money_type')->nullable()->index()->comment('Type of mobile money transaction: send_money, paybill, buy_goods, withdraw');

            $table->foreignId('category_id')
                ->constrained()
                ->onDelete('cascade');

            $table->foreignId('account_id')
                ->constrained()
                ->onDelete('cascade');

            // Soft deletes
            $table->softDeletes();

            // Reversal tracking
            $table->foreignId('reversed_by_transaction_id')
                ->nullable()
                ->constrained('transactions')
                ->onDelete('set null')
                ->comment('If this transaction was reversed, links to the reversal transaction');

            $table->foreignId('reverses_transaction_id')
                ->nullable()
                ->constrained('transactions')
                ->onDelete('set null')
                ->comment('If this is a reversal, links to the original transaction');

            $table->text('reversal_reason')->nullable();
            $table->boolean('is_reversal')->default(false);
            $table->date('period_date')->nullable()->index();

            // Transaction fee tracking
            $table->foreignId('related_fee_transaction_id')
                ->nullable()
                ->constrained('transactions')
                ->onDelete('set null')
                ->comment('Links to the transaction fee record if this is a main transaction');

            $table->foreignId('fee_for_transaction_id')
                ->nullable()
                ->constrained('transactions')
                ->onDelete('cascade')
                ->comment('If this is a fee transaction, links to the main transaction');

            $table->boolean('is_transaction_fee')
                ->default(false)
                ->index()
                ->comment('Identifies if this transaction is a fee for another transaction');


            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
