<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */


class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'                    => User::factory(),
            'account_id'                 => Account::factory(),
            'category_id'                => Category::factory(),
            'date'                       => now()->toDateString(),
            'period_date'                => now()->toDateString(),
            'description'                => $this->faker->sentence(3),
            'amount'                     => $this->faker->randomFloat(2, 10, 5000),
            'payment_method'             => 'Cash',
            'mobile_money_type'          => null,
            'is_transaction_fee'         => false,
            'is_split'                   => false,
            'is_reversal'                => false,
            'related_fee_transaction_id' => null,
            'fee_for_transaction_id'     => null,
        ];
    }
}
