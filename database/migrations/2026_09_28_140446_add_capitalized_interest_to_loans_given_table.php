<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans_given', function (Blueprint $table) {
            $table->decimal('capitalized_interest', 12, 2)
                ->default(0)
                ->after('principal_amount');
        });
    }

    public function down(): void
    {
        Schema::table('loans_given', function (Blueprint $table) {
            $table->dropColumn('capitalized_interest');
        });
    }
};
