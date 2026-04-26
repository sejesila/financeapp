<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClientFund>
 */
class ClientFundFactory extends Factory
{
    public function definition(): array
    {
        $received = $this->faker->randomFloat(2, 1000, 50000);

        return [
            'user_id'         => User::factory(),
            'account_id'      => Account::factory(),
            'client_name'     => $this->faker->name(),
            'type'            => $this->faker->randomElement(['commission', 'no_profit']),
            'amount_received' => $received,
            'amount_spent'    => 0,
            'profit_amount'   => 0,
            'balance'         => $received,
            'status'          => 'pending',
            'purpose'         => $this->faker->sentence(),
            'received_date'   => now()->toDateString(),
            'completed_date'  => null,
            'notes'           => null,
        ];
    }
}
