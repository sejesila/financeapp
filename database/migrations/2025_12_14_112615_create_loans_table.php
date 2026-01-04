<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // 👈 link to users
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->string('source'); // M-Shwari, KCB-Mpesa, Fuliza, etc.

            // Principal & Interest
            $table->decimal('principal_amount', 10, 2);
            $table->decimal('interest_rate', 5, 2)->nullable();
            $table->decimal('interest_amount', 10, 2)->nullable();
            $table->decimal('facility_fee', 10, 2)->nullable();
            $table->decimal('total_amount', 10, 2);

            // Payment tracking
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->decimal('balance', 10, 2);

            // Dates
            $table->date('disbursed_date');
            $table->date('due_date')->nullable();
            $table->date('repaid_date')->nullable();

            // Status
            $table->enum('status', ['active', 'paid', 'defaulted', 'cancelled'])->default('active');

            // Additional info
            $table->text('notes')->nullable();
            $table->string('loan_type')->default('mshwari');
            $table->boolean('is_custom')->default(false);
            $table->decimal('custom_interest_amount', 10, 2)->nullable();

            $table->timestamps();

            // Indexes
            $table->index('account_id');
            $table->index('status');
            $table->index('disbursed_date');
        });



    }

    public function down(): void
    {
        Schema::dropIfExists('loan_payments');
        Schema::dropIfExists('loans');
    }
};
