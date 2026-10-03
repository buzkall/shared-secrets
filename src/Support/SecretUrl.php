<?php

namespace Arzcode\SharedSecrets\Support;

use Arzcode\SharedSecrets\Models\SharedSecret;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

class SecretUrl
{
    public const string ROUTE_NAME = 'shared-secrets.reveal';

    /**
     * The signed link a reader opens. It is rebuilt on demand and never
     * stored: the signature only proves the link was issued by this
     * application, while expiry and views are enforced from the database.
     */
    public static function for(SharedSecret $secret): ?string
    {
        $panel = Filament::getPanels()[$secret->panel] ?? null;

        if ($panel === null) {
            return null;
        }

        $routeName = $panel->generateRouteName(self::ROUTE_NAME);

        if (! Route::has($routeName)) {
            return null;
        }

        return URL::signedRoute($routeName, ['secret' => $secret->getKey()]);
    }
}
