<?php
namespace Database\Seeders;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $user  = User::where('email', 's@s.com')->first();
        $mpesa = Account::withoutGlobalScopes()->where('user_id', $user->id)->where('name', 'Mpesa')->first();
        $bank  = Account::withoutGlobalScopes()->where('user_id', $user->id)->where('name', 'I&M Bank')->first();
        $cash  = Account::withoutGlobalScopes()->where('user_id', $user->id)->where('name', 'Cash')->first();

        $cat = fn(string $name) => Category::where('user_id', $user->id)->where('name', $name)->first();

        for ($monthsAgo = 36; $monthsAgo >= 0; $monthsAgo--) {
            $month = now()->subMonths($monthsAgo);

            // ── INCOME ────────────────────────────────────────────────────────
            // Salary → Bank (KES 90k-100k), grows slightly over 3 years
            $salaryBase = 9000 + (int)(((36 - $monthsAgo) / 36) * 1500); // grows from ~9k to ~10.5k
            $this->create($user, $bank, $cat('Salary'), [
                'date'        => $month->copy()->startOfMonth()->addDay()->toDateString(),
                'amount'      => rand($salaryBase, $salaryBase + 1000),
                'description' => 'Monthly Salary',
            ]);

            // Freelance → Mpesa (occasional, more frequent in recent months)
            if (rand(0, 1)) {
                $this->create($user, $mpesa, $cat('Freelance'), [
                    'date'        => $month->copy()->day(15)->toDateString(),
                    'amount'      => rand(1000, 3500),
                    'description' => 'Freelance Payment - ' . $month->format('M Y'),
                ]);
            }

            // Business Income → Mpesa (occasional)
            if (rand(0, 2) === 0) {
                $this->create($user, $mpesa, $cat('Business Income'), [
                    'date'        => $month->copy()->day(rand(5, 25))->toDateString(),
                    'amount'      => rand(500, 2000),
                    'description' => 'Business Revenue',
                ]);
            }

            // Cash top-up
            $this->create($user, $cash, $cat('Business Income'), [
                'date'        => $month->copy()->day(1)->toDateString(),
                'amount'      => rand(800, 1500),
                'description' => 'Monthly Cash Allowance',
            ]);

            // ── EXPENSES ──────────────────────────────────────────────────────

            // Rent → Bank (fixed, increases once a year)
            $rent = $monthsAgo > 24 ? 1000 : ($monthsAgo > 12 ? 1200 : 1400);
            $this->create($user, $bank, $cat('Rent'), [
                'date'        => $month->copy()->startOfMonth()->addDays(1)->toDateString(),
                'amount'      => $rent,
                'description' => 'Monthly Rent Payment',
            ]);

            // Groceries → Mpesa
            foreach (range(1, rand(2, 3)) as $i) {
                $this->create($user, $mpesa, $cat('Groceries'), [
                    'date'        => $month->copy()->day(rand(1, 28))->toDateString(),
                    'amount'      => rand(250, 600),
                    'description' => 'Grocery Shopping',
                ]);
            }

            // Transport → Mpesa
            foreach (range(1, rand(4, 8)) as $i) {
                $this->create($user, $mpesa, $cat('Transport'), [
                    'date'        => $month->copy()->day(rand(1, 28))->toDateString(),
                    'amount'      => rand(30, 150),
                    'description' => collect(['Uber', 'Matatu', 'Fuel', 'Bolt'])->random(),
                ]);
            }

            // Utilities → Mpesa
            $this->create($user, $mpesa, $cat('Utilities'), [
                'date'        => $month->copy()->day(5)->toDateString(),
                'amount'      => rand(200, 450),
                'description' => 'Electricity Bill',
            ]);
            $this->create($user, $mpesa, $cat('Utilities'), [
                'date'        => $month->copy()->day(7)->toDateString(),
                'amount'      => rand(80, 150),
                'description' => 'Water Bill',
            ]);

            // Airtime → Mpesa
            foreach (range(1, rand(2, 4)) as $i) {
                $this->create($user, $mpesa, $cat('Airtime & Data'), [
                    'date'        => $month->copy()->day(rand(1, 28))->toDateString(),
                    'amount'      => rand(50, 150),
                    'description' => collect(['Safaricom Data Bundle', 'Airtime Top-up'])->random(),
                ]);
            }

            // Food & Dining → Cash
            foreach (range(1, rand(3, 6)) as $i) {
                $this->create($user, $cash, $cat('Food & Dining'), [
                    'date'        => $month->copy()->day(rand(1, 28))->toDateString(),
                    'amount'      => rand(400, 1200),
                    'description' => collect(['Lunch', 'Dinner Out', 'Coffee', 'Restaurant'])->random(),
                ]);
            }

            // Entertainment → Mpesa (occasional)
            if (rand(0, 1)) {
                $this->create($user, $mpesa, $cat('Entertainment'), [
                    'date'        => $month->copy()->day(rand(10, 25))->toDateString(),
                    'amount'      => rand(150, 400),
                    'description' => collect(['Netflix', 'Cinema', 'Event Tickets'])->random(),
                ]);
            }

            // Healthcare → Mpesa (occasional)
            if (rand(0, 3) === 0) {
                $this->create($user, $mpesa, $cat('Healthcare'), [
                    'date'        => $month->copy()->day(rand(1, 28))->toDateString(),
                    'amount'      => rand(100, 500),
                    'description' => collect(['Pharmacy', 'Doctor Visit', 'Lab Tests'])->random(),
                ]);
            }

            // Clothing → Bank (quarterly)
            if ($monthsAgo % 3 === 0) {
                $this->create($user, $bank, $cat('Clothing'), [
                    'date'        => $month->copy()->day(rand(10, 20))->toDateString(),
                    'amount'      => rand(300, 1000),
                    'description' => 'Clothes Shopping',
                ]);
            }

            // Education → Bank (occasional)
            if (rand(0, 4) === 0) {
                $this->create($user, $bank, $cat('Education'), [
                    'date'        => $month->copy()->day(rand(1, 15))->toDateString(),
                    'amount'      => rand(200, 800),
                    'description' => collect(['Online Course', 'Books', 'Workshop'])->random(),
                ]);
            }

            // Savings → Bank (end of month)
            $this->create($user, $bank, $cat('Savings'), [
                'date'        => $month->copy()->endOfMonth()->subDays(2)->toDateString(),
                'amount'      => rand(500, 1500),
                'description' => 'Monthly Savings Transfer',
            ]);
        }

        // Recalculate balances
        foreach ([$mpesa, $bank, $cash] as $account) {
            $account->updateBalance();
        }
    }

    private function create(User $user, Account $account, ?Category $category, array $data): void
    {
        if (!$category) return;

        Transaction::withoutGlobalScopes()->create(array_merge([
            'user_id'     => $user->id,
            'account_id'  => $account->id,
            'category_id' => $category->id,
            'is_reversal' => false,
            'is_split'    => false,
        ], $data));
    }
}
