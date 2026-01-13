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
                    ['name' => 'Dining Out', 'icon' => '🍴'],
                ],
            ],
            [
                'name' => 'Shopping',
                'type' => 'expense',
                'icon' => '🛍️',
                'children' => [
                    ['name' => 'Clothing', 'icon' => '👔'],
                    ['name' => 'Electronics', 'icon' => '📱'],
                    ['name' => 'Personal Care', 'icon' => '💄'],
                ],
            ],
            [
                'name' => 'Household & Appliances',
                'type' => 'expense',
                'icon' => '🏡',
                'children' => [
                    ['name' => 'Furniture', 'icon' => '🛋️'],
                    ['name' => 'Kitchen Appliances', 'icon' => '🍳'],
                    ['name' => 'Home Appliances', 'icon' => '📺'],

                ],
            ],
            [
                'name' => 'Family Support',
                'type' => 'expense',
                'icon' => '👨‍👩‍👧‍👦',
                'children' => [
                    ['name' => 'Better Half', 'icon' => '💑'],
                    ['name' => 'Mum', 'icon' => '👵'],
                    ['name' => 'Sibling', 'icon' => '👫'],

                ],
            ],
            [
                'name' => 'Health',
                'type' => 'expense',
                'icon' => '⚕️',
                'children' => [
                    ['name' => 'Doctor', 'icon' => '👨‍⚕️'],
                    ['name' => 'Pharmacy', 'icon' => '💊'],
                    ['name' => 'Gym & Fitness', 'icon' => '💪'],
                ],
            ],
            [
                'name' => 'Education',
                'type' => 'expense',
                'icon' => '📚',
                'children' => [
                    ['name' => 'School Fees', 'icon' => '🎓'],
                    ['name' => 'Books & Supplies', 'icon' => '📖'],
                ],
            ],
            [
                'name' => 'Miscellaneous',
                'type' => 'expense',
                'icon' => '📦',
                'children' => [
                    ['name' => 'Home Project', 'icon' => '🏗️'],
                    ['name' => 'Other Expenses', 'icon' => '🔹'],
                    ['name' => 'Third Party Purchase', 'icon' => '🔹'],
                ],
            ],
            [
                'name' => 'Income',
                'type' => 'income',
                'icon' => '💰',
                'children' => [
                    ['name' => 'Salary', 'icon' => '💼'],
                    ['name' => 'Side Income', 'icon' => '🏢'],
                    //['name' => 'Freelance', 'icon' => '💻'],
                    //['name' => 'Business', 'icon' => '🏪'],
                    //['name' => 'Investment Returns', 'icon' => '📈'],
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
                    'type'      => $parentData['type'],
                    'parent_id' => $parent->id,
                ]);
            }
        }
    }
}
