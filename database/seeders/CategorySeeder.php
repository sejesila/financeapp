<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 's@s.com')->first();

        $categories = [
            // ── Income ───────────────────────────────────────────────────
            ['name' => 'Salary',           'type' => 'income',    'icon' => '💼'],
            ['name' => 'Freelance',         'type' => 'income',    'icon' => '💻'],
            ['name' => 'Business Income',   'type' => 'income',    'icon' => '🏪'],
            ['name' => 'Investment Returns','type' => 'income',    'icon' => '📈'],
            ['name' => 'Rental Income',     'type' => 'income',    'icon' => '🏠'],

            // ── Expense ───────────────────────────────────────────────────
            ['name' => 'Rent',              'type' => 'expense',   'icon' => '🏠'],
            ['name' => 'Groceries',         'type' => 'expense',   'icon' => '🛒'],
            ['name' => 'Transport',         'type' => 'expense',   'icon' => '🚗'],
            ['name' => 'Utilities',         'type' => 'expense',   'icon' => '💡'],
            ['name' => 'Entertainment',     'type' => 'expense',   'icon' => '🎬'],
            ['name' => 'Healthcare',        'type' => 'expense',   'icon' => '🏥'],
            ['name' => 'Clothing',          'type' => 'expense',   'icon' => '👗'],
            ['name' => 'Airtime & Data',    'type' => 'expense',   'icon' => '📱'],
            ['name' => 'Food & Dining',     'type' => 'expense',   'icon' => '🍽️'],
            ['name' => 'Education',         'type' => 'expense',   'icon' => '📚'],
            ['name' => 'Savings',           'type' => 'expense',   'icon' => '💰'],

            // ── System (required by Account::updateBalance) ────────────────
            ['name' => 'Loan Receipt',      'type' => 'liability', 'icon' => '🏦'],
            ['name' => 'Loan Repayment',    'type' => 'expense',   'icon' => '💳'],
            ['name' => 'Loan Disbursement', 'type' => 'income',    'icon' => '💸'],
            ['name' => 'Balance Adjustment','type' => 'income',    'icon' => '⚖️'],
            ['name' => 'Client Funds',      'type' => 'liability', 'icon' => '🤝'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(
                ['user_id' => $user->id, 'name' => $cat['name']],
                array_merge($cat, ['user_id' => $user->id, 'is_active' => true])
            );
        }
    }
}
