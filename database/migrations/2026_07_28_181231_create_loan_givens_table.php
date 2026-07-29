<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans_given', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('disbursement_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->string('borrower_name');
            $table->string('borrower_contact')->nullable();
            $table->decimal('principal_amount', 10, 2);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->decimal('balance', 10, 2);
            $table->decimal('interest_amount', 10, 2)->nullable();
            $table->decimal('interest_rate', 5, 2)->nullable();
            $table->date('disbursed_date');
            $table->date('due_date')->nullable();
            $table->date('repaid_date')->nullable();
            $table->enum('status', ['active', 'paid', 'defaulted', 'written_off'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('account_id');
            $table->index('status');
            $table->index('disbursed_date');
        });

        Schema::create('loan_given_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('loan_given_id')->constrained('loans_given')->onDelete('cascade');
            $table->foreignId('account_id')->constrained()->onDelete('cascade'); // account the repayment landed in
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('amount', 10, 2);
            $table->date('payment_date');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('loan_given_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_given_payments');
        Schema::dropIfExists('loans_given');
    }
};
