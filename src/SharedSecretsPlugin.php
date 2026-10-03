<?php

namespace Arzcode\SharedSecrets;

use Arzcode\SharedSecrets\Http\Middleware\ProtectRevealResponse;
use Arzcode\SharedSecrets\Pages\ManageSharedSecrets;
use Arzcode\SharedSecrets\Pages\RevealSharedSecret;
use Arzcode\SharedSecrets\Support\SecretUrl;
use BackedEnum;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Route;
use LogicException;
use UnitEnum;

class SharedSecretsPlugin implements Plugin
{
    use EvaluatesClosures;

    public const string ID = 'shared-secrets';

    protected ?Closure $authorizeUsing = null;
    protected bool|Closure $canViewAllSecrets = false;
    protected bool|Closure $hasRecipients = true;
    protected ?Closure $modifyRecipientQueryUsing = null;
    protected string $revealPath = 'secret';
    protected ?string $revealLogoHeight = '3rem';
    protected string|UnitEnum|Closure|null $navigationGroup = null;
    protected ?int $navigationSort = null;
    protected string|BackedEnum|Htmlable|null $navigationIcon = Heroicon::OutlinedKey;

    public static function make(): static
    {
        /** @var static $plugin */
        $plugin = app(static::class);

        return $plugin;
    }

    /**
     * The plugin of the current panel, or null on a panel that did not
     * register it.
     */
    public static function get(): ?static
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        if (! $panel instanceof Panel || ! $panel->hasPlugin(static::ID)) {
            return null;
        }

        /** @var static $plugin */
        $plugin = $panel->getPlugin(static::ID);

        return $plugin;
    }

    public function getId(): string
    {
        return static::ID;
    }

    public function register(Panel $panel): void
    {
        $path = trim($this->revealPath, '/');

        if ($path === '' || $path === ManageSharedSecrets::SLUG) {
            throw new LogicException("The shared secrets reveal path [{$path}] must be filled and differ from the page slug.");
        }

        $panel
            ->pages([ManageSharedSecrets::class])
            ->routes(function() use ($path): void {
                // Public on purpose: the signature proves the link was issued here,
                // and the page itself restricts targeted secrets to their recipient.
                Route::get("{$path}/{secret}", RevealSharedSecret::class)
                    ->middleware(['signed', ProtectRevealResponse::class])
                    ->name(SecretUrl::ROUTE_NAME);
            });
    }

    public function boot(Panel $panel): void {}

    /**
     * Who may share secrets from this panel. Receives the authenticated user.
     * Defaults to every user who can access the panel.
     */
    public function authorize(?Closure $callback): static
    {
        $this->authorizeUsing = $callback;

        return $this;
    }

    public function isAuthorized(?Authenticatable $user = null): bool
    {
        $user ??= Filament::auth()->user();

        if ($user === null) {
            return false;
        }

        if (! $this->authorizeUsing instanceof Closure) {
            return true;
        }

        return (bool)$this->evaluate($this->authorizeUsing, ['user' => $user]);
    }

    /**
     * Let a user see the secrets sent by everyone instead of only their own.
     * The content stays unreadable and the link stays hidden either way, and
     * only its sender can revoke or delete a secret.
     */
    public function viewAllSecrets(bool|Closure $condition = true): static
    {
        $this->canViewAllSecrets = $condition;

        return $this;
    }

    public function canViewAllSecrets(): bool
    {
        return (bool)$this->evaluate($this->canViewAllSecrets, ['user' => Filament::auth()->user()]);
    }

    /**
     * Whether a secret can be targeted at a user of the site. When off, every
     * secret is for anyone with the link.
     */
    public function recipients(bool|Closure $condition = true): static
    {
        $this->hasRecipients = $condition;

        return $this;
    }

    public function hasRecipients(): bool
    {
        return (bool)$this->evaluate($this->hasRecipients);
    }

    /**
     * Narrow down who can be picked as a recipient. Receives the user query.
     */
    public function recipientQuery(?Closure $callback): static
    {
        $this->modifyRecipientQueryUsing = $callback;

        return $this;
    }

    public function getRecipientQueryModifier(): ?Closure
    {
        return $this->modifyRecipientQueryUsing;
    }

    /**
     * The URL segment, inside the panel path, of the link a reader opens.
     */
    public function revealPath(string $path): static
    {
        $this->revealPath = $path;

        return $this;
    }

    /**
     * The height of the brand logo on the page a reader opens, as a CSS
     * length. Null keeps the height the panel uses everywhere else.
     */
    public function revealLogoHeight(?string $height): static
    {
        $this->revealLogoHeight = $height;

        return $this;
    }

    public function getRevealLogoHeight(): ?string
    {
        // Only a plain CSS length ever reaches the page's style tag.
        return is_string($this->revealLogoHeight) && preg_match('/^\d+(\.\d+)?(px|rem|em|vh|%)$/', $this->revealLogoHeight) === 1
            ? $this->revealLogoHeight
            : null;
    }

    public function navigationGroup(string|UnitEnum|Closure|null $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): string|UnitEnum|null
    {
        $group = $this->evaluate($this->navigationGroup);

        return is_string($group) || $group instanceof UnitEnum ? $group : null;
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort;
    }

    public function navigationIcon(string|BackedEnum|Htmlable|null $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return $this->navigationIcon;
    }
}
