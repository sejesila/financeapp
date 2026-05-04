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
            'Rent'           => 1200,
            'Groceries'      => 1500,
            'Transport'      => 800,
            'Utilities'      => 600,
            'Entertainment'  => 400,
            'Airtime & Data' => 300,
            'Food & Dining'  => 1000,
        ];

        for ($monthsAgo = 36; $monthsAgo >= 0; $monthsAgo--) {
            $date  = now()->subMonths($monthsAgo);
            $year  = $date->year;
            $month = $date->month;

            foreach ($budgetCategories as $categoryName => $baseAmount) {
                $category = Category::where('user_id', $user->id)
                    ->where('name', $categoryName)
                    ->first();

                if (!$category) continue;

                // Slightly vary budgets month to month and grow them over time
                $growth = (int)(((36 - $monthsAgo) / 36) * ($baseAmount * 0.15));
                $amount = $baseAmount + $growth + rand(-50, 50);

                Budget::withoutGlobalScopes()->firstOrCreate(
                    [
                        'user_id'     => $user->id,
                        'category_id' => $category->id,
                        'year'        => $year,
                        'month'       => $month,
                    ],
                    ['amount' => max(100, $amount)]
                );
            }
        }
    }
}
