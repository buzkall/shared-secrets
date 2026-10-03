<?php

namespace Arzcode\SharedSecrets\Support;

use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;

/**
 * Works out which panels a targeted user can reach, to decide where they log
 * in and whether a database notification would ever be seen.
 */
class RecipientPanels
{
    /**
     * @return array<Panel>
     */
    public static function accessibleTo(Model $user): array
    {
        return array_values(array_filter(
            Filament::getPanels(),
            fn(Panel $panel): bool => ! $user instanceof FilamentUser || $user->canAccessPanel($panel)
        ));
    }

    public static function hasDatabaseNotifications(Model $user): bool
    {
        return array_any(
            static::accessibleTo($user),
            fn(Panel $panel): bool => $panel->hasDatabaseNotifications()
        );
    }

    /**
     * The login page to send a guest to, preferring the panel the secret was
     * created in when the recipient can access it.
     */
    public static function loginUrl(Model $user, ?Panel $preferred = null): ?string
    {
        $panels = static::accessibleTo($user);

        usort($panels, fn(Panel $a, Panel $b): int => ($b === $preferred) <=> ($a === $preferred));

        foreach ($panels as $panel) {
            if ($panel->hasLogin()) {
                return $panel->getLoginUrl();
            }
        }

        return null;
    }
}
