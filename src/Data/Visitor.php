<?php

namespace Arzcode\SharedSecrets\Data;

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;

/**
 * Who is acting on a secret, as recorded in the audit log.
 */
final readonly class Visitor
{
    public function __construct(
        public ?Authenticatable $user = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {}

    public static function current(): self
    {
        $userAgent = request()->userAgent();

        return new self(
            user: Filament::auth()->user(),
            ipAddress: request()->ip(),
            userAgent: $userAgent === null ? null : Str::limit($userAgent, 500, ''),
        );
    }

    public function userId(): int|string|null
    {
        $id = $this->user?->getAuthIdentifier();

        return is_int($id) || is_string($id) ? $id : null;
    }
}
