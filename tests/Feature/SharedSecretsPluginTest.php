<?php

use Arzcode\SharedSecrets\Pages\ManageSharedSecrets;
use Arzcode\SharedSecrets\SharedSecretsPlugin;
use Arzcode\SharedSecrets\Tests\Fixtures\User;
use Filament\Facades\Filament;
use Filament\Panel;

use function Pest\Laravel\actingAs;

it('refuses a reveal path that would collide with the page or is empty', function(string $path): void {
    $plugin = SharedSecretsPlugin::make()->revealPath($path);

    expect(fn() => $plugin->register(Panel::make()->id('other')))->toThrow(LogicException::class);
})->with([
    'the page slug' => 'shared-secrets',
    'empty'         => '',
    'only slashes'  => '/',
]);

it('authorizes every panel user when no callback is set', function(): void {
    $plugin = SharedSecretsPlugin::make();

    expect($plugin->isAuthorized(User::factory()->make()))->toBeTrue()
        ->and($plugin->isAuthorized())->toBeFalse();
});

it('gives no access to the page on a panel that did not register the plugin', function(): void {
    actingAs(User::factory()->create());
    Filament::setCurrentPanel(Filament::getPanel('client'));

    expect(SharedSecretsPlugin::get())->toBeNull()
        ->and(ManageSharedSecrets::canAccess())->toBeFalse();
});

it('only accepts a plain CSS length as the logo height of the reader page', function(?string $height, ?string $expected): void {
    expect(SharedSecretsPlugin::make()->revealLogoHeight($height)->getRevealLogoHeight())->toBe($expected);
})->with([
    'rem'             => ['4rem', '4rem'],
    'pixels'          => ['48px', '48px'],
    'panel default'   => [null, null],
    'style injection' => ['3rem } body { display: none', null],
]);
