<?php

namespace Arzcode\SharedSecrets\Database\Factories;

use Arzcode\SharedSecrets\Enums\SharedSecretEventType;
use Arzcode\SharedSecrets\Models\SharedSecret;
use Arzcode\SharedSecrets\Models\SharedSecretEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SharedSecretEvent>
 */
class SharedSecretEventFactory extends Factory
{
    protected $model = SharedSecretEvent::class;

    public function definition(): array
    {
        return [
            'shared_secret_id' => SharedSecret::factory(),
            'type'             => SharedSecretEventType::Viewed,
            'user_id'          => null,
            'ip_address'       => fake()->ipv4(),
            'user_agent'       => fake()->userAgent()
        ];
    }

    public function failedPassphrase(): static
    {
        return $this->state(fn(): array => ['type' => SharedSecretEventType::PassphraseFailed]);
    }
}
