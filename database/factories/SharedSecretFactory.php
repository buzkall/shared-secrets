<?php

namespace Arzcode\SharedSecrets\Database\Factories;

use Arzcode\SharedSecrets\Enums\SharedSecretStatus;
use Arzcode\SharedSecrets\Models\SharedSecret;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<SharedSecret>
 */
class SharedSecretFactory extends Factory
{
    protected $model = SharedSecret::class;

    public function definition(): array
    {
        return [
            'panel'                   => 'admin',
            'creator_id'              => null,
            'recipient_id'            => null,
            'content'                 => fake()->password(16),
            'note'                    => null,
            'passphrase'              => null,
            'max_views'               => 5,
            'views_count'             => 0,
            'expires_at'              => now()->addWeek(),
            'requires_retrieval_step' => true,
            'allows_deletion'         => true,
            'closed_at'               => null,
            'closed_reason'           => null
        ];
    }

    public function from(Model $creator): static
    {
        return $this->state(fn(): array => ['creator_id' => $creator->getKey()]);
    }

    public function to(Model $recipient): static
    {
        return $this->state(fn(): array => ['recipient_id' => $recipient->getKey()]);
    }

    public function withPassphrase(string $passphrase): static
    {
        return $this->state(fn(): array => ['passphrase' => Hash::make($passphrase)]);
    }

    public function withoutRetrievalStep(): static
    {
        return $this->state(fn(): array => ['requires_retrieval_step' => false]);
    }

    public function withoutDeletion(): static
    {
        return $this->state(fn(): array => ['allows_deletion' => false]);
    }

    public function singleView(): static
    {
        return $this->state(fn(): array => ['max_views' => 1]);
    }

    public function overdue(): static
    {
        return $this->state(fn(): array => ['expires_at' => now()->subMinute()]);
    }

    public function closed(SharedSecretStatus $reason = SharedSecretStatus::Revoked): static
    {
        return $this->state(fn(): array => [
            'content'       => null,
            'passphrase'    => null,
            'closed_at'     => now(),
            'closed_reason' => $reason
        ]);
    }
}
