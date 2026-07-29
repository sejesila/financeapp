<?php

namespace Database\Factories;

use App\Models\Referrer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Referrer>
 */
class ReferrerFactory extends Factory
{
    protected $model = Referrer::class;

    public function definition(): array
    {
        return [
            'user_id'                  => User::factory(),
            'name'                     => fake()->name(),
            'contact'                  => fake()->boolean(80) ? fake()->phoneNumber() : null,
            'default_share_percentage' => fake()->randomFloat(2, 5, 30),
            'is_active'                => true,
        ];
    }

    /**
     * Referrer no longer being offered as an option on new loans.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Attach to a specific user instead of generating a new one —
     * handy when seeding referrers alongside an existing test user's loans.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
