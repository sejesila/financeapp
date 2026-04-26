<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'year' => date('Y'),
            'month' => $this->faker->numberBetween(1, 12),
            'amount' => $this->faker->randomFloat(2, 100, 5000),
        ];
    }
}
