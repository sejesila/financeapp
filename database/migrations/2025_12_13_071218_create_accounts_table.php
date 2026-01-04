<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // 👈 link to users table
            $table->string('name');
            $table->string('slug')->nullable();
            $table->enum('type', ['cash', 'mpesa', 'airtel_money', 'bank'])->default('cash');
            $table->decimal('initial_balance', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->string('currency', 3)->default('KES');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->index('slug');
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('accounts');
    }
};
