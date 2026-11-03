<?php

namespace Arzcode\SharedSecrets\Support;

use Arzcode\SharedSecrets\Models\SharedSecret;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Route;

class SecretUrl
{
    public const string ROUTE_NAME = 'shared-secrets.reveal';

    /**
     * The signed link a reader opens. It is rebuilt on demand and never
     * stored: the signature only proves the link was issued by this
     * application, while expiry and views are enforced from the database.
     * When the secret's language has its own domain, the link is signed for
     * that domain, so the reader lands on the site in their language.
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

        return static::generatorFor(static::hostFor($secret, $panel))
            ->signedRoute($routeName, ['secret' => $secret->getKey()]);
    }

    /**
     * The host of the secret's language, unless the panel is bound to its own
     * domains: its routes would not answer on any other host.
     */
    public static function hostFor(SharedSecret $secret, Panel $panel): ?string
    {
        return $panel->getDomains() === [] ? Languages::hostFor($secret->chosenLocale()) : null;
    }

    /**
     * A URL generator rooted at the host, so a signature covers it, built
     * apart from the application's own so its root URL never changes for the
     * rest of the request. It keeps the route defaults (a tenant, say) and
     * signs with the application key, as the signed middleware expects.
     */
    public static function generatorFor(?string $host): UrlGenerator
    {
        $generator = app(UrlGenerator::class);

        if ($host === null) {
            return $generator;
        }

        $root = str_contains($host, '://')
            ? $host
            : static::scheme($generator) . '://' . $host;

        $hosted = new UrlGenerator(Route::getRoutes(), Request::create($root . '/'), Cast::nullableString(config('app.asset_url')));
        $hosted->setKeyResolver(fn(): string => config()->string('app.key'));
        $hosted->defaults($generator->getDefaultParameters());

        return $hosted;
    }

    /**
     * The scheme of a host given without one: the application URL's, which
     * a request behind an untrusted proxy would misreport, then the request's.
     */
    protected static function scheme(UrlGenerator $generator): string
    {
        $scheme = parse_url(Cast::nullableString(config('app.url')) ?? '', PHP_URL_SCHEME);

        return is_string($scheme) && in_array($scheme, ['http', 'https'], true) ? $scheme : $generator->getRequest()->getScheme();
    }
}
