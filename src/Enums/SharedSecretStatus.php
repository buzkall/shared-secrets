<?php

namespace Arzcode\SharedSecrets\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The state of a secret. Every case but Active is also the reason a secret
 * was closed, stored in the closed_reason column.
 */
enum SharedSecretStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Expired = 'expired';
    case Exhausted = 'exhausted';
    case Revoked = 'revoked';
    case Deleted = 'deleted';

    public function getLabel(): string
    {
        return __("shared-secrets::shared-secrets.status.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active                   => 'success',
            self::Expired, self::Exhausted => 'gray',
            self::Revoked, self::Deleted   => 'warning'
        };
    }
}
