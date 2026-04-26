<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'     => User::factory(),
            'name'        => $this->faker->unique()->word(),
            'type'        => $this->faker->randomElement(['income', 'expense', 'liability']),
            'icon'        => '💰',
            'is_active'   => true,
            'parent_id'   => null,
            'usage_count' => 0,
        ];
    }
}
