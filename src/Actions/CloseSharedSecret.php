<?php

namespace Arzcode\SharedSecrets\Actions;

use Arzcode\SharedSecrets\Data\Visitor;
use Arzcode\SharedSecrets\Enums\SharedSecretEventType;
use Arzcode\SharedSecrets\Enums\SharedSecretStatus;
use Arzcode\SharedSecrets\Models\SharedSecret;

class CloseSharedSecret
{
    /**
     * Wipe the payload and passphrase and keep the row for the audit log.
     * Closing an already closed secret changes nothing.
     */
    public function handle(
        SharedSecret $secret,
        SharedSecretStatus $reason,
        ?SharedSecretEventType $event = null,
        ?Visitor $visitor = null,
    ): void {
        if ($secret->closed_at !== null) {
            return;
        }

        $secret->forceFill([
            'content'       => null,
            'passphrase'    => null,
            'closed_at'     => now(),
            'closed_reason' => $reason
        ])->save();

        if ($event instanceof SharedSecretEventType) {
            $secret->recordEvent($event, $visitor);
        }
    }
}
