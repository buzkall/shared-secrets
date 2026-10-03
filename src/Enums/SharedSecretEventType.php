<?php

namespace Arzcode\SharedSecrets\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SharedSecretEventType: string implements HasColor, HasLabel
{
    case Viewed = 'viewed';
    case PassphraseFailed = 'passphrase_failed';
    case DeletedByRecipient = 'deleted_by_recipient';
    case Revoked = 'revoked';

    public function getLabel(): string
    {
        return __("shared-secrets::shared-secrets.events.types.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Viewed                            => 'success',
            self::PassphraseFailed                  => 'danger',
            self::DeletedByRecipient, self::Revoked => 'warning'
        };
    }
}
