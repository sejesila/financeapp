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
                    ['name' => 'Electricity', 'icon' => '💡'],
                    ['name' => 'Internet and Communication', 'icon' => '📶'],
                    ['name' => 'Drinking Water', 'icon' => '💧'],
                    ['name' => 'Cooking Gas', 'icon' => '🔥'],
                ],
            ],
            [
                'name' => 'Transportation',
                'type' => 'expense',
                'icon' => '🚗',
                'children' => [
                    ['name' => 'Fare', 'icon' => '🚕'],
                ],
            ],
            [
                'name' => 'Food',
                'type' => 'expense',
                'icon' => '🍽️',
                'children' => [
                    ['name' => 'Groceries', 'icon' => '🛒'],
                ],
            ],
            [
                'name' => 'Family Support',
                'type' => 'expense',
                'icon' => '👨‍👩‍👧‍👦',
                'children' => [
                    ['name' => 'Spouse', 'icon' => '💑'],
                    ['name' => 'Parent', 'icon' => '👵'],
                    ['name' => 'Siblings', 'icon' => '👫'],
                   
                ],
            ],
            [
                'name' => 'Miscellaneous',
                'type' => 'expense',
                'icon' => '📦',
                'children' => [
                    ['name' => 'Home Project', 'icon' => '🎬'],
                    ['name' => 'Other', 'icon' => '🔹'],
                ],
            ],
//            [
//                'name' => 'Health',
//                'type' => 'expense',
//                'icon' => '⚕️',
//                'children' => [
//                    ['name' => 'Doctor', 'icon' => '👨‍⚕️'],
//                    ['name' => 'Pharmacy', 'icon' => '💊'],
//                    ['name' => 'Insurance', 'icon' => '🏥'],
//                ],
//            ],
            [
                'name' => 'Income',
                'type' => 'income',
                'icon' => '💰',
                'children' => [
                    ['name' => 'Salary', 'icon' => '💼'],
                    ['name' => 'Side Income', 'icon' => '🏢'],
                ],
            ],
            [
                'name' => 'Loans',
                'type' => 'liability',
                'icon' => '💳',
                'children' => [
                    ['name' => 'M-Shwari', 'icon' => '📱'],
                    ['name' => 'KCB Mpesa', 'icon' => '📱'],
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
