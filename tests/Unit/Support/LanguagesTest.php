<?php

use Arzcode\SharedSecrets\Actions\CreateSharedSecret;
use Arzcode\SharedSecrets\Support\Languages;
use Arzcode\SharedSecrets\Tests\Fixtures\Language;
use Arzcode\SharedSecrets\Tests\Fixtures\LanguageModel;
use Arzcode\SharedSecrets\Tests\Fixtures\User;
use Illuminate\Support\Facades\Lang;

it('offers no languages when no source is configured', function(): void {
    expect(Languages::enabled())->toBeFalse()
        ->and(Languages::options())->toBe([])
        ->and(Languages::isValid('es'))->toBeFalse();
});

it('reads the languages of a backed enum with their labels', function(): void {
    config()->set('shared-secrets.languages.source', Language::class);

    expect(Languages::enabled())->toBeTrue()
        ->and(Languages::options())->toBe(['en' => 'English', 'es' => 'Español', 'ca' => 'Català', 'ba' => 'Balear'])
        ->and(Languages::label('ca'))->toBe('Català');
});

it('reads the languages of a model with its configured attributes', function(): void {
    config()->set('shared-secrets.languages.source', LanguageModel::class);
    LanguageModel::query()->create(['code' => 'es', 'name' => 'Spanish']);
    LanguageModel::query()->create(['code' => 'en', 'name' => 'English']);

    expect(Languages::options())->toBe(['en' => 'English', 'es' => 'Spanish']);
});

it('rejects a source that is neither a backed enum nor a model', function(): void {
    config()->set('shared-secrets.languages.source', 'NotAClass');

    Languages::options();
})->throws(LogicException::class);

it('only accepts a locale offered by the source', function(): void {
    config()->set('shared-secrets.languages.source', Language::class);

    expect(Languages::isValid('es'))->toBeTrue()
        ->and(Languages::isValid('fr'))->toBeFalse()
        ->and(Languages::isValid(null))->toBeFalse();
});

it('applies only a valid locale', function(): void {
    config()->set('shared-secrets.languages.source', Language::class);

    Languages::apply('fr');
    expect(app()->getLocale())->toBe('en');

    Languages::apply('es');
    expect(app()->getLocale())->toBe('es');
});

it('drops a locale removed from the source when creating the secret', function(): void {
    config()->set('shared-secrets.languages.source', Language::class);
    $user = User::factory()->create();

    $secret = app(CreateSharedSecret::class)->handle(
        creator: $user,
        panelId: 'admin',
        content: 'hunter2',
        expiresInMinutes: 60,
        maxViews: 1,
        locale: 'fr'
    );

    expect($secret->locale)->toBeNull();
});

it('falls back to the locale mapped to a regional variant', function(): void {
    config()->set('shared-secrets.languages.source', Language::class);
    config()->set('shared-secrets.languages.fallbacks', ['ba' => 'ca']);
    Lang::setFallback('es');

    Languages::apply('ba');

    expect(app()->getLocale())->toBe('ba')
        ->and(Lang::getFallback())->toBe('ca')
        ->and(__('shared-secrets::shared-secrets.reveal.actions.reveal'))->toBe('Revela el secret');
});

it('keeps the fallback of a locale that is not mapped', function(): void {
    config()->set('shared-secrets.languages.source', Language::class);
    Lang::setFallback('es');

    Languages::apply('ba');

    expect(Lang::getFallback())->toBe('es');
});

it('restores the locale and the fallback after running in a language', function(): void {
    config()->set('shared-secrets.languages.source', Language::class);
    config()->set('shared-secrets.languages.fallbacks', ['ba' => 'ca']);
    Lang::setFallback('es');

    $text = Languages::using('ba', fn(): string => __('shared-secrets::shared-secrets.reveal.actions.reveal'));

    expect($text)->toBe('Revela el secret')
        ->and(app()->getLocale())->toBe('en')
        ->and(Lang::getFallback())->toBe('es');
});
