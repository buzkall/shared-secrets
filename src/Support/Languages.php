<?php

namespace Arzcode\SharedSecrets\Support;

use Arzcode\SharedSecrets\Models\SharedSecret;
use BackedEnum;
use Closure;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Schema;
use LogicException;

/**
 * Reads the languages a secret can be shown in from the host's enum or
 * model, when one is configured.
 */
class Languages
{
    public const string HOSTS_BINDING = 'shared-secrets.languages.hosts';

    /**
     * @return class-string<BackedEnum>|class-string<Model>|null
     */
    public static function source(): ?string
    {
        $source = config('shared-secrets.languages.source');

        if ($source === null) {
            return null;
        }

        if (is_string($source) && (is_subclass_of($source, BackedEnum::class) || is_subclass_of($source, Model::class))) {
            return $source;
        }

        throw new LogicException('The shared-secrets languages source must be a backed enum or an Eloquent model.');
    }

    public static function configured(): bool
    {
        return static::source() !== null;
    }

    /**
     * Languages are only picked and stored once the upgrade migration has
     * added the column, so configuring a source before running it never
     * breaks pushing a secret.
     */
    public static function enabled(): bool
    {
        return static::configured() && static::isStored();
    }

    /**
     * A source is configured but the column it needs does not exist yet.
     */
    public static function needsMigration(): bool
    {
        return static::configured() && ! static::isStored();
    }

    public static function isStored(): bool
    {
        $table = new SharedSecret()->getTable();

        return once(fn(): bool => Schema::hasColumn($table, 'locale'));
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $source = static::source();

        return once(fn(): array => match (true) {
            $source === null                           => [],
            is_subclass_of($source, BackedEnum::class) => static::enumOptions($source),
            is_subclass_of($source, Model::class)      => static::modelOptions($source),
            default                                    => []
        });
    }

    public static function label(?string $locale): ?string
    {
        return $locale === null ? null : static::options()[$locale] ?? null;
    }

    /**
     * Only an offered locale that the translator accepts is ever applied, so
     * a value removed from the source since never breaks a page.
     */
    public static function isValid(?string $locale): bool
    {
        return $locale !== null
            && static::enabled()
            && preg_match('/^[A-Za-z0-9_-]+$/', $locale) === 1
            && array_key_exists($locale, static::options());
    }

    /**
     * The locale whose strings fill in the ones a regional variant does not
     * translate, from the fallbacks map in the config.
     */
    public static function fallbackFor(string $locale): ?string
    {
        $fallback = config()->array('shared-secrets.languages.fallbacks', [])[$locale] ?? null;

        return is_string($fallback) && preg_match('/^[A-Za-z0-9_-]+$/', $fallback) === 1 ? $fallback : null;
    }

    /**
     * The host a secret in this locale is linked from, for sites that serve
     * each language from its own domain. Looked up by the locale, then by its
     * fallback, so a regional variant shares its base language's domain.
     * Null keeps the link on the application's own URL.
     */
    public static function hostFor(?string $locale): ?string
    {
        if ($locale === null || ! static::isValid($locale)) {
            return null;
        }

        $hosts = static::hosts();
        $fallback = static::fallbackFor($locale);

        return static::validHost($hosts[$locale] ?? null)
            ?? ($fallback === null ? null : static::validHost($hosts[$fallback] ?? null));
    }

    /**
     * The locale => host map from the config: either the map itself or a
     * static method returning it, given as [Class::class, 'method'] so that
     * config:cache can still serialise it. A method is called once per
     * request or queued job, not once per link.
     *
     * @return array<array-key, mixed>
     */
    public static function hosts(): array
    {
        $hosts = app(self::HOSTS_BINDING);

        return is_array($hosts) ? $hosts : [];
    }

    /**
     * Reads the hosts setting. Only a [Class::class, 'method'] pair or a
     * closure is ever called, so a string that happens to name a function
     * is ignored rather than run.
     */
    public static function resolveHosts(): mixed
    {
        $hosts = config('shared-secrets.languages.hosts');

        if ($hosts instanceof Closure || (is_array($hosts) && array_is_list($hosts) && is_callable($hosts))) {
            return $hosts();
        }

        return $hosts;
    }

    /**
     * A bare host (example.cat, example.cat:8080) or a scheme and host
     * (https://example.cat). Anything with a path or a query would produce a
     * link whose signature never matches, so it keeps the application URL.
     */
    protected static function validHost(mixed $host): ?string
    {
        return is_string($host) && preg_match('~^(https?://)?[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)*(:\d{1,5})?/?$~', $host) === 1
            ? rtrim($host, '/')
            : null;
    }

    public static function apply(?string $locale): void
    {
        if ($locale === null || ! static::isValid($locale)) {
            return;
        }

        app()->setLocale($locale);

        $fallback = static::fallbackFor($locale);

        if ($fallback !== null) {
            Lang::setFallback($fallback);
        }
    }

    /**
     * Runs the callback in the locale and its fallback, then restores both,
     * so a notification never leaves them changed for the rest of the
     * request or the queue worker.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function using(?string $locale, Closure $callback): mixed
    {
        $previousLocale = app()->getLocale();
        $previousFallback = Lang::getFallback();

        try {
            static::apply($locale);

            return $callback();
        } finally {
            app()->setLocale($previousLocale);
            Lang::setFallback($previousFallback);
        }
    }

    /**
     * @param  class-string<BackedEnum>  $enum
     * @return array<string, string>
     */
    protected static function enumOptions(string $enum): array
    {
        $options = [];

        foreach ($enum::cases() as $case) {
            $label = $case instanceof HasLabel ? $case->getLabel() : null;

            $options[(string)$case->value] = is_string($label) && filled($label) ? $label : $case->name;
        }

        return $options;
    }

    /**
     * @param  class-string<Model>  $model
     * @return array<string, string>
     */
    protected static function modelOptions(string $model): array
    {
        $value = config()->string('shared-secrets.languages.value_attribute', 'code');
        $label = config()->string('shared-secrets.languages.label_attribute', 'name');
        $options = [];

        foreach ($model::query()->orderBy($label)->get([$value, $label]) as $language) {
            $key = $language->getAttribute($value);
            $text = $language->getAttribute($label);

            if (is_scalar($key) && filled($key)) {
                $options[(string)$key] = is_scalar($text) && filled($text) ? (string)$text : (string)$key;
            }
        }

        return $options;
    }
}
