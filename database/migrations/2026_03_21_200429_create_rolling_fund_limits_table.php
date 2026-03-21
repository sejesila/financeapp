<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rolling_fund_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('monthly_stake_limit', 15, 2)->nullable()->comment('Max total staked per calendar month');
            $table->decimal('single_stake_limit', 15, 2)->nullable()->comment('Max amount per individual session');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('user_id'); // one limit config per user
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rolling_fund_limits');
    }
};
