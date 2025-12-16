<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {


        $incomeCategories = [
            'Salary',
            'Side Income',
        ];

        $expenseCategories = [
            'Transport',
            'Cooking Gas',
            'Miete',
            'Savings',
            'Groceries',
            'Electricity',
            'Water',
            'School',
            'Better Half',
            'Debt Repayment',
            'Family Support',
            'Internet and Communication',
            'Miscellaneous',
        ];

        $liabilityCategories = [
            'M-Shwari',
            'KCB MPESA',
            'Other Loan Source',
        ];

        foreach ($incomeCategories as $name) {
            Category::firstOrCreate(['name' => $name, 'type' => 'income']);
        }

        foreach ($expenseCategories as $name) {
            Category::firstOrCreate(['name' => $name, 'type' => 'expense']);
        }

        foreach ($liabilityCategories as $name) {
            Category::firstOrCreate(['name' => $name, 'type' => 'liability']);
        }

    }
}
