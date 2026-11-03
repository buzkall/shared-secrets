<?php

namespace Arzcode\SharedSecrets\Tests\Fixtures;

use Filament\Support\Contracts\HasLabel;

enum Language: string implements HasLabel
{
    case En = 'en';
    case Es = 'es';
    case Ca = 'ca';

    // A regional variant the package ships no translations for.
    case Ba = 'ba';

    public function getLabel(): string
    {
        return match ($this) {
            self::En => 'English',
            self::Es => 'Español',
            self::Ca => 'Català',
            self::Ba => 'Balear'
        };
    }

    /**
     * @return array<string, string>
     */
    public static function hosts(): array
    {
        return ['ca' => 'example.cat', 'es' => 'example.es'];
    }
}
