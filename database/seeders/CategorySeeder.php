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
        if (!$user) {
            $this->command->error('User with email s@s.com not found!');
            return;
        }
        $this->command->info('Found user: ' . $user->email);

        $tree = [
            // ── Income ────────────────────────────────────────────────────
            ['name' => 'Income', 'type' => 'income', 'icon' => '💰', 'children' => [
                ['name' => 'Salary',            'type' => 'income', 'icon' => '💼'],
                ['name' => 'Freelance',          'type' => 'income', 'icon' => '💻'],
                ['name' => 'Business Income',    'type' => 'income', 'icon' => '🏪'],
                ['name' => 'Investment Returns', 'type' => 'income', 'icon' => '📈'],
                ['name' => 'Rental Income',      'type' => 'income', 'icon' => '🏠'],
                ['name' => 'Side Income',        'type' => 'income', 'icon' => '🤑'],
            ]],

            // ── Housing ───────────────────────────────────────────────────
            ['name' => 'Housing', 'type' => 'expense', 'icon' => '🏠', 'children' => [
                ['name' => 'Rent',       'type' => 'expense', 'icon' => '🏠'],
                ['name' => 'Utilities',  'type' => 'expense', 'icon' => '💡'],
                ['name' => 'Electricity','type' => 'expense', 'icon' => '⚡'],
                ['name' => 'Water',      'type' => 'expense', 'icon' => '💧'],
            ]],

            // ── Food ──────────────────────────────────────────────────────
            ['name' => 'Food', 'type' => 'expense', 'icon' => '🍽️', 'children' => [
                ['name' => 'Groceries',   'type' => 'expense', 'icon' => '🛒'],
                ['name' => 'Food & Dining','type' => 'expense', 'icon' => '🍽️'],
            ]],

            // ── Transport ─────────────────────────────────────────────────
            ['name' => 'Transport', 'type' => 'expense', 'icon' => '🚗', 'children' => [
                ['name' => 'Transport', 'type' => 'expense', 'icon' => '🚗'],
                ['name' => 'Fuel',      'type' => 'expense', 'icon' => '⛽'],
            ]],

            // ── Communication ─────────────────────────────────────────────
            ['name' => 'Communication', 'type' => 'expense', 'icon' => '📱', 'children' => [
                ['name' => 'Airtime & Data',          'type' => 'expense', 'icon' => '📱'],
                ['name' => 'Internet and Communication','type' => 'expense', 'icon' => '🌐'],
            ]],

            // ── Lifestyle ─────────────────────────────────────────────────
            ['name' => 'Lifestyle', 'type' => 'expense', 'icon' => '🎬', 'children' => [
                ['name' => 'Entertainment', 'type' => 'expense', 'icon' => '🎬'],
                ['name' => 'Clothing',      'type' => 'expense', 'icon' => '👗'],
                ['name' => 'Healthcare',    'type' => 'expense', 'icon' => '🏥'],
                ['name' => 'Education',     'type' => 'expense', 'icon' => '📚'],
                ['name' => 'School Fees',   'type' => 'expense', 'icon' => '🎓'],
            ]],

            // ── Savings & Investments ─────────────────────────────────────
            ['name' => 'Savings & Investments', 'type' => 'expense', 'icon' => '💰', 'children' => [
                ['name' => 'Savings', 'type' => 'expense', 'icon' => '💰'],
            ]],

            // ── Other ─────────────────────────────────────────────────────
            ['name' => 'Other', 'type' => 'expense', 'icon' => '📦', 'children' => [
                ['name' => 'Other Expenses',  'type' => 'expense', 'icon' => '📦'],
                ['name' => 'Transaction Fees','type' => 'expense', 'icon' => '🏧'],
            ]],

            // ── System ───────────────────────────────────────────────────
            ['name' => 'Loans', 'type' => 'liability', 'icon' => '🏦', 'children' => [
                ['name' => 'Loan Receipt',      'type' => 'liability', 'icon' => '🏦'],
                ['name' => 'Loan Repayment',    'type' => 'expense',   'icon' => '💳'],
                ['name' => 'Loan Disbursement', 'type' => 'income',    'icon' => '💸'],
            ]],
            ['name' => 'System', 'type' => 'income', 'icon' => '⚙️', 'children' => [
                ['name' => 'Balance Adjustment', 'type' => 'income',    'icon' => '⚖️'],
                ['name' => 'Client Funds',       'type' => 'liability', 'icon' => '🤝'],
            ]],
        ];

        foreach ($tree as $parentData) {
            $children = $parentData['children'];
            unset($parentData['children']);

            $parent = Category::firstOrCreate(
                ['user_id' => $user->id, 'name' => $parentData['name'], 'parent_id' => null],
                array_merge($parentData, ['user_id' => $user->id, 'is_active' => true])
            );

            foreach ($children as $childData) {
                Category::firstOrCreate(
                    ['user_id' => $user->id, 'name' => $childData['name'], 'parent_id' => $parent->id],
                    array_merge($childData, ['user_id' => $user->id, 'is_active' => true, 'parent_id' => $parent->id])
                );
            }
        }
    }
}
