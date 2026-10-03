<?php

namespace Arzcode\SharedSecrets\Pages;

use Arzcode\SharedSecrets\Actions\CloseSharedSecret;
use Arzcode\SharedSecrets\Actions\ConsumeSharedSecret;
use Arzcode\SharedSecrets\Data\Visitor;
use Arzcode\SharedSecrets\Enums\RevealOutcome;
use Arzcode\SharedSecrets\Enums\SharedSecretEventType;
use Arzcode\SharedSecrets\Enums\SharedSecretStatus;
use Arzcode\SharedSecrets\Models\SharedSecret;
use Arzcode\SharedSecrets\SharedSecretsPlugin;
use Arzcode\SharedSecrets\Support\Cast;
use Arzcode\SharedSecrets\Support\RecipientPanels;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use LogicException;

class RevealSharedSecret extends SimplePage
{
    /**
     * The simple layout would otherwise show the panel's user menu and
     * notifications to a logged-in visitor.
     */
    protected bool $hasTopbar = false;

    #[Locked]
    public string $secretId = '';

    #[Locked]
    public bool $isRevealed = false;

    #[Locked]
    public bool $isDeleted = false;

    /**
     * How many more times the link can be opened, as of this reader's view.
     */
    #[Locked]
    public ?int $remainingViews = null;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /**
     * Deliberately not public: the plaintext is rendered once, by the request
     * that spent the view, and never enters the Livewire snapshot.
     */
    protected ?string $revealedContent = null;

    protected ?SharedSecret $secret = null;
    protected bool $isSecretLoaded = false;

    public function mount(string $secret): void
    {
        $this->secretId = $secret;

        if (! $this->authorizeAccess(redirectsGuests: true)) {
            return;
        }

        $this->formSchema()->fill();
    }

    /**
     * Livewire update requests carry no signature and skip the route
     * middleware, so every later request is authorised again here.
     */
    public function hydrate(): void
    {
        $this->authorizeAccess(redirectsGuests: false);
    }

    public function getTitle(): string
    {
        return __('shared-secrets::shared-secrets.reveal.title');
    }

    public function getHeading(): string
    {
        return __('shared-secrets::shared-secrets.reveal.title');
    }

    public function getSubheading(): ?Htmlable
    {
        $text = $this->subheadingText();

        // A translation breaks the line with a newline; the text itself stays escaped.
        return $text === null ? null : new HtmlString(nl2br(e($text)));
    }

    protected function subheadingText(): ?string
    {
        if ($this->isRevealed && ! $this->isDeleted) {
            return trans_choice('shared-secrets::shared-secrets.reveal.revealed.subheading', $this->remainingViews ?? 0, [
                'count' => $this->remainingViews ?? 0
            ]);
        }

        if (! $this->isPending()) {
            return null;
        }

        if ($this->needsPassphrase()) {
            return __('shared-secrets::shared-secrets.reveal.passphrase.subheading');
        }

        return __('shared-secrets::shared-secrets.reveal.confirm.subheading');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('passphrase')
                    ->label(__('shared-secrets::shared-secrets.reveal.passphrase.label'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->autocomplete(false)
                    ->autofocus()
                    ->visible(fn(): bool => $this->needsPassphrase())
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Html::make(fn(): HtmlString => $this->logoStyles()),

            // Loading the page never spends a view: without the retrieval step the
            // browser asks for the secret itself, which a scanner fetching the link does not.
            Html::make(new HtmlString('<div wire:init="reveal"></div>'))
                ->visible(fn(): bool => $this->revealsOnLoad()),

            Text::make(__('shared-secrets::shared-secrets.reveal.unavailable'))
                ->visible(fn(): bool => ! $this->isRevealed && ! $this->isDeleted && ! $this->isPending()),

            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('reveal')
                ->footer([
                    Actions::make([
                        Action::make('reveal')
                            ->label(__('shared-secrets::shared-secrets.reveal.actions.reveal'))
                            ->submit('reveal')
                    ])->fullWidth()
                ])
                ->visible(fn(): bool => $this->isPending()),

            TextEntry::make('content')
                ->label(__('shared-secrets::shared-secrets.reveal.revealed.label'))
                ->hiddenLabel()
                ->state(fn(): ?string => $this->revealedContent)
                ->formatStateUsing(fn(?string $state): HtmlString => new HtmlString(
                    '<span style="white-space: pre-wrap; overflow-wrap: anywhere;">' . e($state) . '</span>'
                ))
                ->fontFamily(FontFamily::Mono)
                ->suffixAction(
                    Action::make('copySecret')
                        ->label(__('shared-secrets::shared-secrets.reveal.revealed.copy'))
                        ->icon(Heroicon::OutlinedClipboardDocument)
                        ->button()
                        ->color('gray')
                        ->size(Size::Small)
                        ->alpineClickHandler(fn(): string => 'window.navigator.clipboard.writeText(' . Js::from((string)$this->revealedContent) . '); '
                            . '$tooltip(' . Js::from(__('shared-secrets::shared-secrets.reveal.revealed.copied')) . ', { theme: $store.theme })')
                )
                // Later requests render this entry without its state; ignoring it
                // keeps the text the reader is looking at on screen.
                ->extraEntryWrapperAttributes(['wire:ignore' => true])
                ->visible(fn(): bool => $this->isRevealed && ! $this->isDeleted),

            Actions::make([$this->deleteNowAction()])
                ->fullWidth()
                ->visible(fn(): bool => $this->canDeleteNow()),

            Text::make(__('shared-secrets::shared-secrets.reveal.deleted'))
                ->visible(fn(): bool => $this->isDeleted)
        ]);
    }

    public function reveal(): void
    {
        if ($this->isRevealed || $this->isDeleted) {
            return;
        }

        $passphrase = null;

        if ($this->needsPassphrase()) {
            $passphrase = Cast::nullableString($this->formSchema()->getState()['passphrase'] ?? null);
        }

        $this->consume($passphrase);
    }

    public function deleteNowAction(): Action
    {
        return Action::make('deleteNow')
            ->label(__('shared-secrets::shared-secrets.reveal.actions.delete'))
            ->color('danger')
            ->requiresConfirmation()
            // "For everyone" only makes sense when anyone with the link can open it.
            ->modalDescription(fn(): string => $this->secret()?->isTargeted()
                ? __('shared-secrets::shared-secrets.reveal.delete_confirmation_targeted')
                : __('shared-secrets::shared-secrets.reveal.delete_confirmation'))
            ->visible(fn(): bool => $this->canDeleteNow())
            ->action(function(CloseSharedSecret $close): void {
                abort_unless($this->canDeleteNow(), 403);

                $secret = $this->secret();

                if ($secret instanceof SharedSecret) {
                    $close->handle($secret, SharedSecretStatus::Deleted, SharedSecretEventType::DeletedByRecipient, Visitor::current());
                }

                $this->isDeleted = true;
            });
    }

    /**
     * The panel's logo height is sized for a topbar; the reader's page gets its
     * own, without touching the panel configuration.
     */
    protected function logoStyles(): HtmlString
    {
        $height = SharedSecretsPlugin::get()?->getRevealLogoHeight();

        return new HtmlString($height === null
            ? ''
            : "<style>.fi-simple-header .fi-logo { height: {$height} !important; }</style>");
    }

    protected function consume(?string $passphrase): void
    {
        $result = app(ConsumeSharedSecret::class)->handle($this->secretId, $passphrase, Visitor::current());

        // The row changed under the lock; drop the copy loaded before it.
        $this->isSecretLoaded = false;
        $this->formSchema()->fill();

        if ($result->isRevealed()) {
            $this->isRevealed = true;
            $this->revealedContent = $result->content;
            $this->remainingViews = $result->remainingViews;

            return;
        }

        $message = match ($result->outcome) {
            RevealOutcome::PassphraseRequired => __('shared-secrets::shared-secrets.reveal.passphrase.required'),
            RevealOutcome::PassphraseInvalid  => __('shared-secrets::shared-secrets.reveal.passphrase.invalid'),
            RevealOutcome::Throttled          => __('shared-secrets::shared-secrets.reveal.passphrase.throttled'),
            RevealOutcome::LockedOut          => __('shared-secrets::shared-secrets.reveal.passphrase.locked_out'),
            default                           => null
        };

        if ($message !== null) {
            throw ValidationException::withMessages(['data.passphrase' => $message]);
        }
    }

    /**
     * A targeted secret only opens for its recipient. Returns false when the
     * visitor is being sent to a login page instead.
     */
    protected function authorizeAccess(bool $redirectsGuests): bool
    {
        $secret = $this->secret();

        if (! $secret instanceof SharedSecret || ! $secret->isTargeted()) {
            return true;
        }

        $userId = Cast::key(Filament::auth()->id());

        if ($userId === null && $redirectsGuests) {
            $loginUrl = $secret->recipient === null
                ? null
                : RecipientPanels::loginUrl($secret->recipient, Filament::getPanels()[$secret->panel] ?? null);

            abort_if($loginUrl === null, 403);

            redirect()->setIntendedUrl(request()->fullUrl());
            $this->redirect($loginUrl);

            return false;
        }

        abort_unless($secret->isFor($userId), 403);

        return true;
    }

    /**
     * Whether there is still a secret waiting to be revealed by this visitor.
     */
    protected function isPending(): bool
    {
        return ! $this->isRevealed && ! $this->isDeleted && ($this->secret()?->isAvailable() ?? false);
    }

    protected function needsPassphrase(): bool
    {
        return $this->isPending() && ($this->secret()?->hasPassphrase() ?? false);
    }

    protected function revealsOnLoad(): bool
    {
        $secret = $this->secret();

        return $secret instanceof SharedSecret
            && ! $secret->requires_retrieval_step
            && $this->isPending()
            && ! $this->needsPassphrase();
    }

    /**
     * Deleting is only offered to a reader who has actually seen the secret,
     * so the link alone is never enough to destroy it.
     */
    protected function canDeleteNow(): bool
    {
        $secret = $this->secret();

        return $this->isRevealed
            && ! $this->isDeleted
            && $secret instanceof SharedSecret
            && $secret->allows_deletion
            && $secret->closed_at === null;
    }

    protected function secret(): ?SharedSecret
    {
        if (! $this->isSecretLoaded) {
            $this->secret = SharedSecret::query()->with('recipient')->find($this->secretId);
            $this->isSecretLoaded = true;
        }

        return $this->secret;
    }

    protected function formSchema(): Schema
    {
        return $this->getSchema('form') ?? throw new LogicException('The shared secrets reveal form schema is missing.');
    }
}
