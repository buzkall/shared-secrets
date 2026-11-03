<?php

use Arzcode\SharedSecrets\Models\SharedSecret;
use Arzcode\SharedSecrets\Support\Languages;
use Arzcode\SharedSecrets\Support\SecretUrl;
use Arzcode\SharedSecrets\Tests\Fixtures\Language;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\get;

beforeEach(function(): void {
    config()->set('shared-secrets.languages.source', Language::class);
});

it('keeps the link on the application URL when no hosts are configured', function(): void {
    $secret = SharedSecret::factory()->inLocale('es')->create();

    expect(parse_url((string)SecretUrl::for($secret), PHP_URL_HOST))->toBe(parse_url(URL::to('/'), PHP_URL_HOST));
});

it('signs the link for the host of the chosen language', function(): void {
    config()->set('shared-secrets.languages.hosts', ['es' => 'example.es', 'ca' => 'example.cat']);
    $secret = SharedSecret::factory()->inLocale('es')->create();
    $url = (string)SecretUrl::for($secret);

    expect(parse_url($url, PHP_URL_HOST))->toBe('example.es')
        ->and(URL::to('/'))->not->toContain('example.es');

    get($url)->assertOk()->assertSee('Revelar secreto');
});

it('signs the link for the host of the chosen language after other route URLs were built', function(): void {
    config()->set('shared-secrets.languages.hosts', ['es' => 'example.es']);
    $secret = SharedSecret::factory()->inLocale('es')->create();
    route('filament.admin.pages.dashboard');

    expect(parse_url((string)SecretUrl::for($secret), PHP_URL_HOST))->toBe('example.es')
        ->and(parse_url(route('filament.admin.pages.dashboard'), PHP_URL_HOST))->not->toBe('example.es');
});

it('reads the hosts from a static method given as a class and method pair', function(): void {
    config()->set('shared-secrets.languages.hosts', [Language::class, 'hosts']);
    $secret = SharedSecret::factory()->inLocale('ca')->create();

    expect(parse_url((string)SecretUrl::for($secret), PHP_URL_HOST))->toBe('example.cat');
});

it('reads the hosts from a closure', function(): void {
    config()->set('shared-secrets.languages.hosts', Language::hosts(...));
    $secret = SharedSecret::factory()->inLocale('ca')->create();

    expect(parse_url((string)SecretUrl::for($secret), PHP_URL_HOST))->toBe('example.cat');
});

it('calls the hosts method once however many links are built', function(): void {
    $calls = 0;
    config()->set('shared-secrets.languages.hosts', function() use (&$calls): array {
        $calls++;

        return ['es' => 'example.es'];
    });
    $secrets = SharedSecret::factory()->inLocale('es')->count(3)->create();

    $secrets->each(fn(SharedSecret $secret): ?string => SecretUrl::for($secret));

    expect($calls)->toBe(1);
});

it('never calls a hosts setting that is a function name', function(): void {
    config()->set('shared-secrets.languages.hosts', 'phpinfo');
    $secret = SharedSecret::factory()->inLocale('es')->create();

    expect(Languages::hosts())->toBe([])
        ->and(parse_url((string)SecretUrl::for($secret), PHP_URL_HOST))->toBe(parse_url(URL::to('/'), PHP_URL_HOST));
});

it('keeps the scheme given with the host', function(): void {
    config()->set('shared-secrets.languages.hosts', ['es' => 'http://example.es:8080/']);
    $secret = SharedSecret::factory()->inLocale('es')->create();

    expect((string)SecretUrl::for($secret))->toStartWith('http://example.es:8080/admin/');
});

it('uses the scheme of the application URL for a bare host', function(): void {
    config()->set('app.url', 'https://example.com');
    config()->set('shared-secrets.languages.hosts', ['es' => 'example.es']);
    $secret = SharedSecret::factory()->inLocale('es')->create();

    expect((string)SecretUrl::for($secret))->toStartWith('https://example.es/admin/');
});

it('keeps the application URL for a host with a path or a query', function(string $host): void {
    config()->set('shared-secrets.languages.hosts', ['es' => $host]);
    $secret = SharedSecret::factory()->inLocale('es')->create();

    expect(parse_url((string)SecretUrl::for($secret), PHP_URL_HOST))->toBe(parse_url(URL::to('/'), PHP_URL_HOST));
})->with([
    'path'   => ['example.es/foo'],
    'query'  => ['https://example.es?x=1'],
    'port'   => ['example.es:abc'],
    'scheme' => ['ftp://example.es']
]);

it('keeps the application URL when the panel is bound to its own domain', function(): void {
    config()->set('shared-secrets.languages.hosts', ['es' => 'example.es']);
    Filament::getPanel('admin')->domains(['app.example.com']);
    $secret = SharedSecret::factory()->inLocale('es')->create();

    expect(parse_url((string)SecretUrl::for($secret), PHP_URL_HOST))->not->toBe('example.es');
});

it('uses the host of the fallback for a regional variant', function(): void {
    config()->set('shared-secrets.languages.hosts', ['ca' => 'example.cat']);
    config()->set('shared-secrets.languages.fallbacks', ['ba' => 'ca']);
    $secret = SharedSecret::factory()->inLocale('ba')->create();

    expect(parse_url((string)SecretUrl::for($secret), PHP_URL_HOST))->toBe('example.cat');
});

it('uses the host of the fallback when the variant has an empty host', function(mixed $host): void {
    config()->set('shared-secrets.languages.hosts', ['ba' => $host, 'ca' => 'example.cat']);
    config()->set('shared-secrets.languages.fallbacks', ['ba' => 'ca']);
    $secret = SharedSecret::factory()->inLocale('ba')->create();

    expect(parse_url((string)SecretUrl::for($secret), PHP_URL_HOST))->toBe('example.cat');
})->with([
    'empty string' => [''],
    'null'         => [null]
]);

it('keeps the application URL for a language without a host', function(): void {
    config()->set('shared-secrets.languages.hosts', ['ca' => 'example.cat']);
    $secret = SharedSecret::factory()->inLocale('en')->create();

    expect(parse_url((string)SecretUrl::for($secret), PHP_URL_HOST))->toBe(parse_url(URL::to('/'), PHP_URL_HOST));
});

it('rejects a link moved to another language host', function(): void {
    config()->set('shared-secrets.languages.hosts', ['es' => 'example.es']);
    $secret = SharedSecret::factory()->inLocale('es')->create();

    get(str_replace('example.es', 'example.cat', (string)SecretUrl::for($secret)))->assertForbidden();
});
