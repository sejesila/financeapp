<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\LoanGiven;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LoanGiven>
 */
class LoanGivenFactory extends Factory
{
    protected $model = LoanGiven::class;

    public function definition(): array
    {
        $principal = $this->faker->randomFloat(2, 500, 20000);
        $disbursed = $this->faker->dateTimeBetween('-6 months', 'now');

        return [
            'user_id'          => User::factory(),
            'account_id'       => Account::factory(),
            'borrower_name'    => $this->faker->name(),
            'borrower_contact' => $this->faker->phoneNumber(),
            'principal_amount' => $principal,
            'amount_paid'      => 0,
            'balance'          => $principal,
            'interest_amount'  => 0,
            'interest_rate'    => 0,
            'disbursed_date'   => $disbursed,
            'due_date'         => (clone $disbursed)->modify('+30 days'),
            'repaid_date'      => null,
            'status'           => 'active',
            'notes'            => null,
        ];
    }

    /**
     * A loan that's been fully repaid, with a plausible interest amount
     * already reconciled — mirrors what closeAsRepaid() would produce.
     */
    public function paid(): static
    {
        return $this->state(function (array $attributes) {
            $principal = $attributes['principal_amount'];
            $interest  = $this->faker->randomFloat(2, 0, $principal * 0.2);
            $amountPaid = $principal + $interest;

            return [
                'amount_paid'     => $amountPaid,
                'balance'         => 0,
                'interest_amount' => $interest,
                'interest_rate'   => $principal > 0 ? round(($interest / $principal) * 100, 2) : 0,
                'status'          => 'paid',
                'repaid_date'     => $this->faker->dateTimeBetween($attributes['disbursed_date'] ?? '-6 months', 'now'),
            ];
        });
    }

    /**
     * A loan with a partial payment recorded but still active.
     */
    public function partiallyPaid(): static
    {
        return $this->state(function (array $attributes) {
            $principal = $attributes['principal_amount'];
            $paid      = $this->faker->randomFloat(2, 1, $principal - 1);

            return [
                'amount_paid' => $paid,
                'balance'     => round($principal - $paid, 2),
            ];
        });
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => now()->subDays($this->faker->numberBetween(1, 60)),
            'status'   => 'active',
        ]);
    }

    public function defaulted(): static
    {
        return $this->state(fn () => ['status' => 'defaulted']);
    }

    public function writtenOff(): static
    {
        return $this->state(fn () => ['status' => 'written_off']);
    }
}
