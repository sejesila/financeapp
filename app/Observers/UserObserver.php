<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Category;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
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
            Category::create([
                'user_id' => $user->id,
                'name'    => $name,
                'type'    => 'income',
            ]);
        }

        foreach ($expenseCategories as $name) {
            Category::create([
                'user_id' => $user->id,
                'name'    => $name,
                'type'    => 'expense',
            ]);
        }

        foreach ($liabilityCategories as $name) {
            Category::create([
                'user_id' => $user->id,
                'name'    => $name,
                'type'    => 'liability',
            ]);
        }
    }
}
