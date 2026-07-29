<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    private const MAX_BALANCE = 100000;

    /** Running in-memory balance per account_id while seeding, so we never
     *  need to hit the DB mid-loop to check "can this account afford this?" */
    private array $balances = [];

    public function run(): void
    {
        $user  = User::where('email', 's@s.com')->first();
        $mpesa = Account::withoutGlobalScopes()->where('user_id', $user->id)->where('name', 'Mpesa')->first();
        $bank  = Account::withoutGlobalScopes()->where('user_id', $user->id)->where('name', 'I&M Bank')->first();
        $cash  = Account::withoutGlobalScopes()->where('user_id', $user->id)->where('name', 'Cash')->first();

        $cat = fn(string $name) => Category::where('user_id', $user->id)->where('name', $name)->first();

        $this->balances = [
            $mpesa->id => (float) $mpesa->initial_balance,
            $bank->id  => (float) $bank->initial_balance,
            $cash->id  => (float) $cash->initial_balance,
        ];

        for ($monthsAgo = 36; $monthsAgo >= 0; $monthsAgo--) {
            $month = now()->subMonths($monthsAgo);

            // ═══════════════════════════════════════════════════════════════
            // INCOME FIRST — credited before any expense this month is
            // evaluated, so expenses always have funds to draw against.
            // ═══════════════════════════════════════════════════════════════

            // Salary → Bank (grows slightly over 3 years)
            $salaryBase = 9000 + (int) (((36 - $monthsAgo) / 36) * 1500);
            $this->credited($user, $bank, $cat('Salary'), [
                'date'        => $month->copy()->startOfMonth()->addDay()->toDateString(),
                'amount'      => rand($salaryBase, $salaryBase + 1000),
                'description' => 'Monthly Salary',
            ]);

            // Mpesa baseline liquidity — sized to comfortably cover this
            // account's typical monthly spend (groceries/transport/utilities/
            // airtime/etc, ~KES 2,500 on average) rather than leaving it to
            // chance the way the old "occasional Freelance" income did.
            $this->credited($user, $mpesa, $cat('Business Income'), [
                'date'        => $month->copy()->day(2)->toDateString(),
                'amount'      => rand(2800, 3800),
                'description' => 'Mpesa Float Top-up',
            ]);

            // Freelance → Mpesa (occasional bonus on top of the baseline)
            if (rand(0, 1)) {
                $this->credited($user, $mpesa, $cat('Freelance'), [
                    'date'        => $month->copy()->day(15)->toDateString(),
                    'amount'      => rand(1000, 3500),
                    'description' => 'Freelance Payment - ' . $month->format('M Y'),
                ]);
            }

            // Extra Business Income → Mpesa (occasional bonus)
            if (rand(0, 2) === 0) {
                $this->credited($user, $mpesa, $cat('Business Income'), [
                    'date'        => $month->copy()->day(rand(5, 25))->toDateString(),
                    'amount'      => rand(500, 2000),
                    'description' => 'Business Revenue',
                ]);
            }

            // Cash allowance — sized to comfortably cover Food & Dining
            // (~KES 3,600/month on average) instead of the old 800-1500,
            // which was the main reason cash went negative.
            $this->credited($user, $cash, $cat('Business Income'), [
                'date'        => $month->copy()->day(1)->toDateString(),
                'amount'      => rand(3800, 4800),
                'description' => 'Monthly Cash Allowance',
            ]);

            // ═══════════════════════════════════════════════════════════════
            // EXPENSES — debited against whatever's actually available.
            // ═══════════════════════════════════════════════════════════════

            // Rent → Bank (fixed, increases once a year)
            $rent = $monthsAgo > 24 ? 1000 : ($monthsAgo > 12 ? 1200 : 1400);
            $this->debited($user, $bank, $cat('Rent'), [
                'date'        => $month->copy()->startOfMonth()->addDays(1)->toDateString(),
                'amount'      => $rent,
                'description' => 'Monthly Rent Payment',
            ]);

            // Groceries → Mpesa
            foreach (range(1, rand(2, 3)) as $i) {
                $this->debited($user, $mpesa, $cat('Groceries'), [
                    'date'        => $month->copy()->day(rand(1, 28))->toDateString(),
                    'amount'      => rand(250, 600),
                    'description' => 'Grocery Shopping',
                ]);
            }

            // Transport → Mpesa
            foreach (range(1, rand(4, 8)) as $i) {
                $this->debited($user, $mpesa, $cat('Transport'), [
                    'date'        => $month->copy()->day(rand(1, 28))->toDateString(),
                    'amount'      => rand(30, 150),
                    'description' => collect(['Uber', 'Matatu', 'Fuel', 'Bolt'])->random(),
                ]);
            }

            // Utilities → Mpesa
            $this->debited($user, $mpesa, $cat('Utilities'), [
                'date'        => $month->copy()->day(5)->toDateString(),
                'amount'      => rand(200, 450),
                'description' => 'Electricity Bill',
            ]);
            $this->debited($user, $mpesa, $cat('Utilities'), [
                'date'        => $month->copy()->day(7)->toDateString(),
                'amount'      => rand(80, 150),
                'description' => 'Water Bill',
            ]);

            // Airtime → Mpesa
            foreach (range(1, rand(2, 4)) as $i) {
                $this->debited($user, $mpesa, $cat('Airtime & Data'), [
                    'date'        => $month->copy()->day(rand(1, 28))->toDateString(),
                    'amount'      => rand(50, 150),
                    'description' => collect(['Safaricom Data Bundle', 'Airtime Top-up'])->random(),
                ]);
            }

            // Food & Dining → Cash
            foreach (range(1, rand(3, 6)) as $i) {
                $this->debited($user, $cash, $cat('Food & Dining'), [
                    'date'        => $month->copy()->day(rand(1, 28))->toDateString(),
                    'amount'      => rand(400, 1200),
                    'description' => collect(['Lunch', 'Dinner Out', 'Coffee', 'Restaurant'])->random(),
                ]);
            }

            // Entertainment → Mpesa (occasional)
            if (rand(0, 1)) {
                $this->debited($user, $mpesa, $cat('Entertainment'), [
                    'date'        => $month->copy()->day(rand(10, 25))->toDateString(),
                    'amount'      => rand(150, 400),
                    'description' => collect(['Netflix', 'Cinema', 'Event Tickets'])->random(),
                ]);
            }

            // Healthcare → Mpesa (occasional)
            if (rand(0, 3) === 0) {
                $this->debited($user, $mpesa, $cat('Healthcare'), [
                    'date'        => $month->copy()->day(rand(1, 28))->toDateString(),
                    'amount'      => rand(100, 500),
                    'description' => collect(['Pharmacy', 'Doctor Visit', 'Lab Tests'])->random(),
                ]);
            }

            // Clothing → Bank (quarterly)
            if ($monthsAgo % 3 === 0) {
                $this->debited($user, $bank, $cat('Clothing'), [
                    'date'        => $month->copy()->day(rand(10, 20))->toDateString(),
                    'amount'      => rand(300, 1000),
                    'description' => 'Clothes Shopping',
                ]);
            }

            // Education → Bank (occasional)
            if (rand(0, 4) === 0) {
                $this->debited($user, $bank, $cat('Education'), [
                    'date'        => $month->copy()->day(rand(1, 15))->toDateString(),
                    'amount'      => rand(200, 800),
                    'description' => collect(['Online Course', 'Books', 'Workshop'])->random(),
                ]);
            }

            // Savings → Bank (end of month)
            $this->debited($user, $bank, $cat('Savings'), [
                'date'        => $month->copy()->endOfMonth()->subDays(2)->toDateString(),
                'amount'      => rand(500, 1500),
                'description' => 'Monthly Savings Transfer',
            ]);
        }

        // Recalculate balances from the actual seeded rows
        foreach ([$mpesa, $bank, $cash] as $account) {
            $account->updateBalance();
        }
    }

    /**
     * Credit an account, capped so its running balance never exceeds
     * MAX_BALANCE. If the cap leaves no room at all, the transaction is
     * skipped entirely rather than recorded as KES 0.
     */
    private function credited(User $user, Account $account, ?Category $category, array $data): void
    {
        $current  = $this->balances[$account->id] ?? 0.0;
        $headroom = self::MAX_BALANCE - $current;

        if ($headroom <= 0) {
            return; // already at cap, nothing to credit
        }

        $amount = min((float) $data['amount'], $headroom);

        if ($amount < 1) {
            return;
        }

        $this->balances[$account->id] = $current + $amount;
        $this->create($user, $account, $category, array_merge($data, ['amount' => $amount]));
    }

    /**
     * Debit an account, capped so its running balance never goes negative.
     * If funds are insufficient, the expense is scaled down to whatever is
     * actually available rather than pushing the account negative.
     */
    private function debited(User $user, Account $account, ?Category $category, array $data): void
    {
        $current = $this->balances[$account->id] ?? 0.0;

        if ($current <= 0) {
            return; // nothing available at all this month
        }

        $amount = min((float) $data['amount'], $current);

        if ($amount < 1) {
            return;
        }

        $this->balances[$account->id] = $current - $amount;
        $this->create($user, $account, $category, array_merge($data, ['amount' => $amount]));
    }

    private function create(User $user, Account $account, ?Category $category, array $data): void
    {
        if (!$category) return;

        Transaction::withoutGlobalScopes()->create(array_merge([
            'user_id'     => $user->id,
            'account_id'  => $account->id,
            'category_id' => $category->id,
        ], $data));
    }
}
