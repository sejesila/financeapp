<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cafeteria_monthly_spendings', function (Blueprint $table) {
            // Signed carryover from the previous month.
            // Positive = unspent surplus (boosts effective limit)
            // Negative = overspend deficit (reduces effective limit)
            $table->decimal('carryover', 10, 2)
                ->default(0)
                ->after('limit')
                ->comment('Signed carryover from previous month. Positive = surplus, negative = deficit.');
        });
    }

    public function down(): void
    {
        Schema::table('cafeteria_monthly_spendings', function (Blueprint $table) {
            $table->dropColumn('carryover');
        });
    }
};
