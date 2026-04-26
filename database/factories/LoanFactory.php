<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Loan>
 */
class LoanFactory extends Factory
{
    public function definition(): array
    {
        $principal   = $this->faker->randomFloat(2, 1000, 50000);
        $interest    = $this->faker->randomFloat(2, 0, 20);
        $interestAmt = round(($principal * $interest) / 100, 2);
        $total       = $principal + $interestAmt;

        return [
            'user_id'          => User::factory(),
            'account_id'       => Account::factory(),
            'source'           => $this->faker->company(),
            'principal_amount' => $principal,
            'interest_rate'    => $interest,
            'interest_amount'  => $interestAmt,
            'facility_fee'     => null,
            'total_amount'     => $total,
            'amount_paid'      => 0,
            'balance'          => $total,
            'disbursed_date'   => now()->subDays(30)->toDateString(),
            'due_date'         => now()->addDays(60)->toDateString(),
            'repaid_date'      => null,
            'status'           => 'active',
            'loan_type'        => 'mshwari',
            'is_custom'        => false,
            'custom_interest_amount' => null,
            'notes'            => null,
        ];
    }

    public function mshwari(): static
    {
        $principal      = 1000;
        $exciseDuty     = round($principal * 0.015, 2);       // 15.00
        $facilitationFee = round($principal * 0.075, 2);      // 75.00
        $total          = $principal + $facilitationFee;      // 1075.00

        return $this->state(fn() => [
            'source'           => 'M-Shwari',
            'principal_amount' => $principal,
            'interest_rate'    => null,
            'interest_amount'  => $facilitationFee,
            'facility_fee'     => null,
            'total_amount'     => $total,
            'balance'          => $total,
            'loan_type'        => 'mshwari',
            'is_custom'        => false,
        ]);
    }

    public function kcbMpesa(): static
    {
        $principal   = 1000;
        $facilityFee = round($principal * 0.0176, 2);         // 17.60
        $interest    = round(($principal * 7.05) / 100, 2);  // 70.50
        $total       = $principal + $facilityFee + $interest; // 1088.10

        return $this->state(fn() => [
            'source'           => 'KCB M-Pesa',
            'principal_amount' => $principal,
            'interest_rate'    => 7.05,
            'interest_amount'  => $interest,
            'facility_fee'     => $facilityFee,
            'total_amount'     => $total,
            'balance'          => $total,
            'loan_type'        => 'kcb_mpesa',
            'is_custom'        => false,
        ]);
    }

    public function other(float $principal = 5000, float $interestAmount = 500): static
    {
        $total = $principal + $interestAmount;

        return $this->state(fn() => [
            'source'                 => 'Friend',
            'principal_amount'       => $principal,
            'interest_rate'          => null,
            'interest_amount'        => $interestAmount,
            'facility_fee'           => null,
            'custom_interest_amount' => $interestAmount,
            'total_amount'           => $total,
            'balance'                => $total,
            'loan_type'              => 'other',
            'is_custom'              => true,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn(array $attributes) => [
            'status'      => 'paid',
            'repaid_date' => now()->toDateString(),
            'amount_paid' => $attributes['total_amount'],
            'balance'     => 0,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn() => [
            'status'      => 'active',
            'repaid_date' => null,
            'amount_paid' => 0,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn() => [
            'status'   => 'active',
            'due_date' => now()->subDays(5)->toDateString(),
        ]);
    }

    public function disbursedDaysAgo(int $days): static
    {
        return $this->state(fn() => [
            'disbursed_date' => now()->subDays($days)->toDateString(),
        ]);
    }
}
