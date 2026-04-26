<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Account>
 */
class AccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'         => User::factory(),
            'name'            => $this->faker->words(2, true),
            // slug omitted intentionally — Account::creating() always derives
            // it from `name`, so pre-setting it here would bypass that logic.
            'type'            => $this->faker->randomElement(['cash', 'mpesa', 'airtel_money', 'bank', 'savings']),
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'currency'        => 'KES',
            'is_active'       => true,
            'notes'           => null,
            'logo_path'       => null,
        ];
    }
}
