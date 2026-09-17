<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referrers', function (Blueprint $table) {
            // The interest rate applied by default to loans referred by this
            // person, used only to seed a new loan's expected_interest_rate
            // (and, via the overdue rollover, its future recompounded rate).
            // Distinct from default_share_percentage, which is the referrer's
            // cut of whatever interest actually ends up being realized.
            $table->decimal('default_interest_rate', 5, 2)->nullable()->after('default_share_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('referrers', function (Blueprint $table) {
            $table->dropColumn('default_interest_rate');
        });
    }
};
