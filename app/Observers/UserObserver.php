<?php
namespace App\Observers;

use App\Models\Category;
use App\Models\User;

class UserObserver
{
    public function created(User $user): void
    {
        $categoryStructure = [
            [
                'name' => 'Housing',
                'type' => 'expense',
                'icon' => '🏠',
                'children' => [
                    ['name' => 'Rent', 'icon' => '🏠'],
                    ['name' => 'Utilities', 'icon' => '💡'],
                    ['name' => 'Internet', 'icon' => '📶'],
                    ['name' => 'Maintenance', 'icon' => '🔧'],
                ],
            ],
            [
                'name' => 'Transportation',
                'type' => 'expense',
                'icon' => '🚗',
                'children' => [
                    ['name' => 'Fuel', 'icon' => '⛽'],
                    ['name' => 'Taxi/Bus', 'icon' => '🚕'],
                    ['name' => 'Parking', 'icon' => '🅿️'],
                ],
            ],
            [
                'name' => 'Food & Dining',
                'type' => 'expense',
                'icon' => '🍽️',
                'children' => [
                    ['name' => 'Groceries', 'icon' => '🛒'],
                    ['name' => 'Restaurants', 'icon' => '🍴'],
                    ['name' => 'Fast Food', 'icon' => '🍔'],
                ],
            ],
            [
                'name' => 'Health',
                'type' => 'expense',
                'icon' => '⚕️',
                'children' => [
                    ['name' => 'Doctor', 'icon' => '👨‍⚕️'],
                    ['name' => 'Pharmacy', 'icon' => '💊'],
                    ['name' => 'Insurance', 'icon' => '🏥'],
                ],
            ],
            [
                'name' => 'Income',
                'type' => 'income',
                'icon' => '💰',
                'children' => [
                    ['name' => 'Salary', 'icon' => '💼'],
                    ['name' => 'Freelance', 'icon' => '💻'],
                    ['name' => 'Business', 'icon' => '🏢'],
                    ['name' => 'Investments', 'icon' => '📈'],
                ],
            ],
            [
                'name' => 'Loans',
                'type' => 'liability',
                'icon' => '💳',
                'children' => [
                    ['name' => 'M-Shwari', 'icon' => '📱'],
                    ['name' => 'KCB Mpesa', 'icon' => '🏦'],
                    ['name' => 'Other Loan', 'icon' => '💵'],
                ],
            ],
        ];

        foreach ($categoryStructure as $parentData) {
            $parent = Category::create([
                'user_id' => $user->id,
                'name'    => $parentData['name'],
                'icon'    => $parentData['icon'],
                'type'    => $parentData['type'],
            ]);

            foreach ($parentData['children'] as $childData) {
                Category::create([
                    'user_id'   => $user->id,
                    'name'      => $childData['name'],
                    'icon'      => $childData['icon'] ?? null,
                    'type'      => $parentData['type'], // ✅ inherit
                    'parent_id' => $parent->id,
                ]);
            }
        }
    }
}
