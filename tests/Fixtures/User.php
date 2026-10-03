<?php

namespace Arzcode\SharedSecrets\Tests\Fixtures;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Admins reach every panel; clients only the client panel, which has neither
 * the plugin nor database notifications.
 */
class User extends Authenticatable implements FilamentUser
{
    use HasFactory;
    use Notifiable;

    protected $guarded = [];

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'client' || $this->role === 'admin';
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
