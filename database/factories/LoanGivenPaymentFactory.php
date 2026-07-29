<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\LoanGiven;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LoanGivenPayment>
 */
class LoanGivenPaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'        => User::factory(),
            'loan_given_id'  => LoanGiven::factory(),
            'account_id'     => Account::factory(),
            'transaction_id' => Transaction::factory(),
            'amount'         => $this->faker->randomFloat(2, 100, 5000),
            'payment_date'   => $this->faker->dateTimeBetween('-3 months', 'now'),
            'notes'          => null,
        ];
    }
}
