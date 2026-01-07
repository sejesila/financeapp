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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->string('name');
            $table->string('icon', 10)->nullable();

            $table->enum('type', ['income', 'expense', 'liability'])
                ->default('expense');

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('usage_count')->default(0);

            $table->timestamps();

            // Hierarchy-safe uniqueness
            $table->unique(['user_id', 'parent_id', 'name']);

            // Performance indexes
            $table->index(['user_id', 'parent_id']);
            $table->index(['user_id', 'type']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
