<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transfer>
 */
class TransferFactory extends Factory
{
    public function definition(): array
    {
        $fromAccount = Account::factory()->create();

        return [
            'user_id'         => $fromAccount->user_id,
            'from_account_id' => $fromAccount->id,
            'to_account_id'   => Account::factory()->state([
                'user_id' => $fromAccount->user_id,
            ]),
            'amount'          => $this->faker->randomFloat(2, 100, 10000),
            'date'            => now()->toDateString(),
            'value_date'      => null,
            'description'     => null,
        ];
    }
}
