<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_funds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('client_name'); // e.g., "John Doe", "ABC Company"
            $table->enum('type', ['commission', 'no_profit'])->default('no_profit');
            $table->decimal('amount_received', 15, 2); // Total received from client
            $table->decimal('amount_spent', 15, 2)->default(0); // What you've spent
            $table->decimal('profit_amount', 15, 2)->default(0); // Your profit
            $table->decimal('balance', 15, 2); // Remaining to return/fulfill
            $table->enum('status', ['pending', 'partial', 'completed', 'cancelled'])->default('pending');
            $table->foreignId('account_id')->constrained(); // Which account received the money
            $table->text('purpose')->nullable(); // e.g., "Laptop purchase", "Office supplies"
            $table->date('received_date');
            $table->date('completed_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });


    }

    public function down(): void
    {

        Schema::dropIfExists('client_funds');
    }
};
