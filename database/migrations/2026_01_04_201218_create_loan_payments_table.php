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
        Schema::create('loan_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // 👈 link to users
            $table->foreignId('loan_id')->constrained()->onDelete('cascade');
            $table->foreignId('account_id')->constrained()->onDelete('cascade');

            $table->decimal('amount', 10, 2);
            $table->decimal('principal_portion', 10, 2)->default(0);
            $table->decimal('interest_portion', 10, 2)->default(0);

            $table->date('payment_date');
            $table->foreignId('transaction_id')->nullable()->constrained()->onDelete('set null');

            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('loan_id');
            $table->index('payment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_payments');
    }
};
