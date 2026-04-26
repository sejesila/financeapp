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
        $principal = $this->faker->randomFloat(2, 1000, 50000);
        $interest  = $this->faker->randomFloat(2, 0, 20);
        $interestAmt = round(($principal * $interest) / 100, 2);
        $total     = $principal + $interestAmt;

        return [
            'user_id'          => User::factory(),
            'account_id'       => Account::factory(),
            'source'           => $this->faker->company(),
            'principal_amount' => $principal,
            'interest_rate'    => $interest,
            'interest_amount'  => $interestAmt,
            'total_amount'     => $total,
            'amount_paid'      => 0,
            'balance'          => $total,
            'disbursed_date'   => now()->subDays(30)->toDateString(),
            'due_date'         => now()->addDays(60)->toDateString(),
            'repaid_date'      => null,
            'status'           => 'active',
            'notes'            => null,
            'loan_type'        => 'personal',
        ];
    }
}
