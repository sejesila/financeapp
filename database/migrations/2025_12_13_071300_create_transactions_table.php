<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->string('payment_method')->nullable();
            //$table->enum('type', ['income', 'expense'])->default('expense');

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

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
