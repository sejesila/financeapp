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
        // Add spending limit columns to users table
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('cafeteria_monthly_limit', 10, 2)
                ->default(10000)
                ->after('remember_token')
                ->comment('Monthly cafeteria spending limit in KES');

            // Tracks the last time the limit was changed — enforces once-per-month edits.
            // Null = never been set, so the first edit is always allowed.
            $table->timestamp('cafeteria_limit_updated_at')
                ->nullable()
                ->after('cafeteria_monthly_limit')
                ->comment('Timestamp of the last limit change — allows one edit per calendar month');
        });

        // Create a table to track monthly spending
        Schema::create('cafeteria_monthly_spendings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('year');
            $table->integer('month');
            $table->decimal('total_spent', 10, 2)->default(0);
            $table->decimal('limit', 10, 2);
            $table->timestamps();

            // Ensure one record per user per month
            $table->unique(['user_id', 'year', 'month']);

            // Indexes for fast queries
            $table->index(['user_id', 'year', 'month']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cafeteria_monthly_spendings');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'cafeteria_limit_updated_at')) {
                $table->dropColumn('cafeteria_limit_updated_at');
            }
            if (Schema::hasColumn('users', 'cafeteria_monthly_limit')) {
                $table->dropColumn('cafeteria_monthly_limit');
            }
        });
    }
};
