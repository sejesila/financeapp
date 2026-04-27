<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // database/migrations/xxxx_add_sacco_dividends_category.php

    public function up(): void
    {
        $users = \App\Models\User::all();

        foreach ($users as $user) {
            $income = \App\Models\Category::where('user_id', $user->id)
                ->where('name', 'Income')
                ->whereNull('parent_id')
                ->first();

            if (!$income) continue;

            $exists = \App\Models\Category::where('user_id', $user->id)
                ->where('name', 'Sacco Dividends')
                ->exists();

            if (!$exists) {
                \App\Models\Category::create([
                    'user_id'   => $user->id,
                    'name'      => 'Sacco Dividends',
                    'type'      => 'income',
                    'icon'      => '🏦',
                    'parent_id' => $income->id,
                ]);
            }
        }
    }

    public function down(): void
    {
        \App\Models\Category::where('name', 'Sacco Dividends')->delete();
    }
};
