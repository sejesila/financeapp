<?php

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class BudgetSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 's@s.com')->first();

        $budgetCategories = [
            'Rent'           => 250,
            'Groceries'      => 150,
            'Transport'      => 80,
            'Utilities'      => 500,
            'Entertainment'  => 40,
            'Airtime & Data' => 20,
            'Food & Dining'  => 60,
        ];

        // Seed budgets for the last 13 months (covers annual report + current month)
        for ($monthsAgo = 12; $monthsAgo >= 0; $monthsAgo--) {
            $date  = now()->subMonths($monthsAgo);
            $year  = $date->year;
            $month = $date->month;

            foreach ($budgetCategories as $categoryName => $amount) {
                $category = Category::where('user_id', $user->id)
                    ->where('name', $categoryName)
                    ->first();

                if (!$category) continue;

                Budget::withoutGlobalScopes()->firstOrCreate(
                    [
                        'user_id'     => $user->id,
                        'category_id' => $category->id,
                        'year'        => $year,
                        'month'       => $month,
                    ],
                    [
                        // Vary amounts slightly month to month to look realistic
                        'amount' => $amount + rand(-1000, 1000),
                    ]
                );
            }
        }
    }
}
