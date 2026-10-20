<?php

namespace Arzcode\SharedSecrets\Actions;

use Arzcode\SharedSecrets\Models\SharedSecret;
use Arzcode\SharedSecrets\Support\Languages;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use SensitiveParameter;

class CreateSharedSecret
{
    public function __construct(protected NotifyRecipient $notify) {}

    public function handle(
        Authenticatable $creator,
        string $panelId,
        #[SensitiveParameter]
        string $content,
        int $expiresInMinutes,
        int $maxViews,
        ?string $note = null,
        #[SensitiveParameter]
        ?string $passphrase = null,
        bool $requiresRetrievalStep = true,
        bool $allowsDeletion = true,
        ?Model $recipient = null,
        ?string $locale = null,
    ): SharedSecret {
        $secret = SharedSecret::query()->create([
            'panel'                   => $panelId,
            'creator_id'              => $creator->getAuthIdentifier(),
            'recipient_id'            => $recipient?->getKey(),
            'content'                 => $content,
            'note'                    => filled($note) ? $note : null,
            'passphrase'              => filled($passphrase) ? Hash::make($passphrase) : null,
            'max_views'               => $maxViews,
            'expires_at'              => now()->addMinutes($expiresInMinutes),
            'requires_retrieval_step' => $requiresRetrievalStep,
            'allows_deletion'         => $allowsDeletion,
            // Only written when chosen, so a host that has not run the upgrade migration keeps working.
            ...(Languages::isValid($locale) ? ['locale' => $locale] : [])
        ]);

        if ($recipient instanceof Model) {
            $this->notify->handle($secret, $recipient, $creator);
        }

        return $secret;
    }
}
