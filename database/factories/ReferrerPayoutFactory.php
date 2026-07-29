<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Referrer;
use App\Models\ReferrerPayout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferrerPayout>
 */
class ReferrerPayoutFactory extends Factory
{
    protected $model = ReferrerPayout::class;

    public function definition(): array
    {
        $periodStart = fake()->dateTimeBetween('-6 months', '-1 month');
        $periodEnd   = (clone $periodStart)->modify('+1 month');

        $totalInterest   = fake()->randomFloat(2, 1000, 50000);
        $sharePercentage = fake()->randomFloat(2, 5, 30);
        $amountPaid      = round($totalInterest * ($sharePercentage / 100), 2);

        return [
            'user_id'          => User::factory(),
            'referrer_id'      => Referrer::factory(),
            'period_start'     => $periodStart,
            'period_end'       => $periodEnd,
            'total_interest'   => $totalInterest,
            'share_percentage' => $sharePercentage,
            'amount_paid'      => $amountPaid,
            'account_id'       => Account::factory(),
            'transaction_id'   => null,
            'paid_date'        => fake()->dateTimeBetween($periodEnd, 'now'),
        ];
    }

    /**
     * A payout that's been calculated but not actually sent yet —
     * no paid_date, no linked transaction.
     */
    public function unpaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'paid_date'      => null,
            'transaction_id' => null,
        ]);
    }

    /**
     * Attach to a specific referrer instead of generating a new one.
     */
    public function forReferrer(Referrer $referrer): static
    {
        return $this->state(fn (array $attributes) => [
            'referrer_id' => $referrer->id,
            'user_id'     => $referrer->user_id,
        ]);
    }
}
