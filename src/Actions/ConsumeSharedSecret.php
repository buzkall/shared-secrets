<?php

namespace Arzcode\SharedSecrets\Actions;

use Arzcode\SharedSecrets\Data\RevealResult;
use Arzcode\SharedSecrets\Data\Visitor;
use Arzcode\SharedSecrets\Enums\RevealOutcome;
use Arzcode\SharedSecrets\Enums\SharedSecretEventType;
use Arzcode\SharedSecrets\Enums\SharedSecretStatus;
use Arzcode\SharedSecrets\Models\SharedSecret;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use SensitiveParameter;

class ConsumeSharedSecret
{
    public function __construct(protected CloseSharedSecret $close) {}

    /**
     * Spend one view and hand back the plaintext. The row is locked for the
     * whole check-and-increment so concurrent readers can never exceed
     * max_views.
     */
    public function handle(string $secretId, #[SensitiveParameter] ?string $passphrase, Visitor $visitor): RevealResult
    {
        return DB::transaction(function() use ($secretId, $passphrase, $visitor): RevealResult {
            $secret = SharedSecret::query()->lockForUpdate()->find($secretId);

            if (! $secret instanceof SharedSecret || $secret->closed_at !== null) {
                return new RevealResult(RevealOutcome::Unavailable);
            }

            if ($secret->isTargeted() && ! $secret->isFor($visitor->userId())) {
                return new RevealResult(RevealOutcome::Unavailable);
            }

            if ($secret->expires_at->isPast()) {
                $this->close->handle($secret, SharedSecretStatus::Expired);

                return new RevealResult(RevealOutcome::Unavailable);
            }

            if ($secret->hasPassphrase()) {
                $outcome = $this->checkPassphrase($secret, $passphrase, $visitor);

                if ($outcome instanceof RevealOutcome) {
                    return new RevealResult($outcome);
                }
            }

            try {
                $content = $this->decrypt($secret);
            } catch (DecryptException) {
                $content = null;
            }

            if ($content === null) {
                return new RevealResult(RevealOutcome::Unavailable);
            }

            $secret->increment('views_count');
            $secret->recordEvent(SharedSecretEventType::Viewed, $visitor);

            if ($secret->views_count >= $secret->max_views) {
                $this->close->handle($secret, SharedSecretStatus::Exhausted);
            }

            return new RevealResult(RevealOutcome::Revealed, $content, max(0, $secret->max_views - $secret->views_count));
        });
    }

    /**
     * A payload encrypted with a rotated-out key cannot be read any more.
     *
     * @throws DecryptException
     */
    protected function decrypt(SharedSecret $secret): ?string
    {
        return $secret->content;
    }

    protected function checkPassphrase(SharedSecret $secret, #[SensitiveParameter] ?string $passphrase, Visitor $visitor): ?RevealOutcome
    {
        if (blank($passphrase)) {
            return RevealOutcome::PassphraseRequired;
        }

        if ($this->isLockedOut($secret)) {
            return RevealOutcome::LockedOut;
        }

        $throttleKey = "shared-secrets:passphrase:{$secret->id}:{$visitor->ipAddress}";

        if (RateLimiter::tooManyAttempts($throttleKey, config()->integer('shared-secrets.passphrase.attempts_per_minute', 5))) {
            return RevealOutcome::Throttled;
        }

        if (Hash::check($passphrase, (string)$secret->passphrase)) {
            return null;
        }

        RateLimiter::hit($throttleKey);
        $secret->recordEvent(SharedSecretEventType::PassphraseFailed, $visitor);

        return RevealOutcome::PassphraseInvalid;
    }

    /**
     * Too many recent wrong passphrases, from any address, pause the secret
     * instead of wiping it: the link alone must never be enough to destroy
     * a secret its reader has not opened yet.
     */
    protected function isLockedOut(SharedSecret $secret): bool
    {
        $lockoutStart = now()->subMinutes(config()->integer('shared-secrets.passphrase.lockout_minutes', 60));

        $failures = $secret->events()
            ->where('type', SharedSecretEventType::PassphraseFailed)
            ->where('created_at', '>', $lockoutStart)
            ->count();

        return $failures >= config()->integer('shared-secrets.passphrase.max_failures', 10);
    }
}
