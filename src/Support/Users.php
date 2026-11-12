<?php

namespace Arzcode\SharedSecrets\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Resolves the host application's user model, so the package never
 * hard-codes App\Models\User.
 */
class Users
{
    /**
     * How many users the recipient select loads before anything is typed.
     */
    public const int OPTIONS_LIMIT = 50;

    /**
     * @return class-string<Model>
     */
    public static function model(): string
    {
        // The key ships as null, so a config() default would never apply.
        $model = config('shared-secrets.user_model') ?? config('auth.providers.users.model');

        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            throw new LogicException('The shared-secrets user model must be an Eloquent model.');
        }

        return $model;
    }

    public static function table(): string
    {
        return (new (static::model()))->getTable();
    }

    public static function label(Model $user): string
    {
        $label = $user->getAttribute(static::titleAttribute());

        return is_scalar($label) && filled($label) ? (string)$label : Cast::string($user->getKey());
    }

    /**
     * The users offered before anything is typed.
     *
     * @param  (Closure(Builder<Model>): mixed)|null  $modifyQueryUsing
     * @param  (Closure(Model): string)|null  $labelUsing
     * @return array<int|string, string>
     */
    public static function options(?Closure $modifyQueryUsing = null, int $limit = self::OPTIONS_LIMIT, ?Closure $labelUsing = null): array
    {
        return static::search(null, $modifyQueryUsing, $limit, $labelUsing);
    }

    /**
     * @param  (Closure(Builder<Model>): mixed)|null  $modifyQueryUsing
     * @param  (Closure(Model): string)|null  $labelUsing
     * @return array<int|string, string>
     */
    public static function search(?string $search, ?Closure $modifyQueryUsing = null, int $limit = self::OPTIONS_LIMIT, ?Closure $labelUsing = null): array
    {
        $labelUsing ??= static::label(...);

        $columns = static::searchColumns();

        return static::query($modifyQueryUsing)
            ->when(filled($search), fn(Builder $query) => $query->where(function(Builder $query) use ($columns, $search): void {
                foreach ($columns as $column) {
                    $query->orWhereLike($column, "%{$search}%");
                }
            }))
            ->orderBy(static::titleAttribute())
            ->limit($limit)
            ->get()
            ->mapWithKeys(fn(Model $user): array => [static::key($user) => $labelUsing($user)])
            ->all();
    }

    /**
     * Whether some users are left out of the options, so typing has to search
     * the database instead of filtering the loaded options in the browser.
     *
     * @param  (Closure(Builder<Model>): mixed)|null  $modifyQueryUsing
     */
    public static function exceedOptions(?Closure $modifyQueryUsing = null, int $limit = self::OPTIONS_LIMIT): bool
    {
        return static::query($modifyQueryUsing)->count() > $limit;
    }

    /**
     * @param  (Closure(Builder<Model>): mixed)|null  $modifyQueryUsing
     */
    public static function find(int|string|null $id, ?Closure $modifyQueryUsing = null): ?Model
    {
        if (blank($id)) {
            return null;
        }

        return static::query($modifyQueryUsing)->whereKey($id)->first();
    }

    /**
     * @param  (Closure(Builder<Model>): mixed)|null  $modifyQueryUsing
     * @return Builder<Model>
     */
    protected static function query(?Closure $modifyQueryUsing): Builder
    {
        $query = static::model()::query();

        if ($modifyQueryUsing instanceof Closure) {
            $modifyQueryUsing($query);
        }

        return $query;
    }

    protected static function key(Model $user): int|string
    {
        $key = $user->getKey();

        return is_int($key) || is_string($key) ? $key : '';
    }

    protected static function titleAttribute(): string
    {
        return config()->string('shared-secrets.user_title_attribute', 'name');
    }

    /**
     * @return array<string>
     */
    protected static function searchColumns(): array
    {
        return array_values(array_filter(
            config()->array('shared-secrets.user_search_columns', ['name', 'email']),
            is_string(...)
        ));
    }
}
