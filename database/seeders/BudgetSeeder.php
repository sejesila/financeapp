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
            'Rent'           => 25000,
            'Groceries'      => 15000,
            'Transport'      => 8000,
            'Utilities'      => 5000,
            'Entertainment'  => 4000,
            'Airtime & Data' => 2000,
            'Food & Dining'  => 6000,
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
