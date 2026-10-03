<?php

namespace Arzcode\SharedSecrets\Tests\Fixtures;

use Arzcode\SharedSecrets\SharedSecretsPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The plugin options read test-only config keys, so a test can flip them
 * without registering another panel.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->pages([Dashboard::class])
            ->databaseNotifications(fn(): bool => (bool)config('testing.database_notifications', true))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class])
            ->plugin(
                SharedSecretsPlugin::make()
                    ->navigationGroup(fn(): string => 'Tools')
                    ->authorize(fn(User $user): bool => ! (bool)config('testing.forbidden', false))
                    ->viewAllSecrets(fn(): bool => (bool)config('testing.view_all', false))
                    ->recipients(fn(): bool => (bool)config('testing.recipients', true))
                    ->recipientQuery(fn($query) => $query->where('email', 'not like', '%@blocked.test'))
            );
    }
}
