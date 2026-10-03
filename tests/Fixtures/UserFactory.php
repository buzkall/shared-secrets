<?php

namespace Arzcode\SharedSecrets\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name'     => fake()->name(),
            'email'    => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role'     => 'admin',
        ];
    }

    public function admin(): static
    {
        return $this->state(fn(): array => ['role' => 'admin']);
    }

    public function client(): static
    {
        return $this->state(fn(): array => ['role' => 'client']);
    }
}
