<?php

namespace Arzcode\SharedSecrets\Pages;

use Arzcode\SharedSecrets\Actions\CloseSharedSecret;
use Arzcode\SharedSecrets\Actions\CreateSharedSecret;
use Arzcode\SharedSecrets\Data\Visitor;
use Arzcode\SharedSecrets\Enums\SharedSecretEventType;
use Arzcode\SharedSecrets\Enums\SharedSecretStatus;
use Arzcode\SharedSecrets\Models\SharedSecret;
use Arzcode\SharedSecrets\Models\SharedSecretEvent;
use Arzcode\SharedSecrets\SharedSecretsPlugin;
use Arzcode\SharedSecrets\Support\Cast;
use Arzcode\SharedSecrets\Support\SecretUrl;
use Arzcode\SharedSecrets\Support\Users;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Js;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use LogicException;
use UnitEnum;

class ManageSharedSecrets extends Page implements HasTable
{
    use InteractsWithTable;

    public const string SLUG = 'shared-secrets';

    protected static ?string $slug = self::SLUG;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /**
     * Only the id of the secret just pushed is kept between requests; its
     * link is rebuilt on render and its content never comes back.
     */
    #[Locked]
    public ?string $createdSecretId = null;

    public function mount(): void
    {
        $this->formSchema()->fill();
    }

    public static function canAccess(): bool
    {
        return static::plugin()?->isAuthorized() ?? false;
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return static::plugin()?->getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return static::plugin()?->getNavigationSort();
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return static::plugin()?->getNavigationIcon();
    }

    public static function getNavigationLabel(): string
    {
        return __('shared-secrets::shared-secrets.navigation.label');
    }

    public function getTitle(): string
    {
        return __('shared-secrets::shared-secrets.page.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('shared-secrets::shared-secrets.form.heading'))
                    ->description(__('shared-secrets::shared-secrets.form.description'))
                    ->columns(2)
                    ->schema([
                        Textarea::make('content')
                            ->label(__('shared-secrets::shared-secrets.form.content.label'))
                            ->placeholder(__('shared-secrets::shared-secrets.form.content.placeholder'))
                            ->required()
                            ->rows(6)
                            ->maxLength(config()->integer('shared-secrets.content_max_length', 10000))
                            ->autocomplete(false)
                            ->columnSpanFull(),

                        Select::make('expires_in')
                            ->label(__('shared-secrets::shared-secrets.form.expires_in.label'))
                            ->options(fn(): array => static::expiryOptions())
                            ->default(config()->integer('shared-secrets.expiry.default', 10080))
                            ->selectablePlaceholder(false)
                            ->required(),

                        static::maxViewsField(),

                        TextInput::make('passphrase')
                            ->label(__('shared-secrets::shared-secrets.form.passphrase.label'))
                            ->helperText(__('shared-secrets::shared-secrets.form.passphrase.helper'))
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->maxLength(255),

                        TextInput::make('note')
                            ->label(__('shared-secrets::shared-secrets.form.note.label'))
                            ->helperText(__('shared-secrets::shared-secrets.form.note.helper'))
                            ->maxLength(255),

                        Toggle::make('requires_retrieval_step')
                            ->label(__('shared-secrets::shared-secrets.form.requires_retrieval_step.label'))
                            ->helperText(__('shared-secrets::shared-secrets.form.requires_retrieval_step.helper'))
                            ->default(true),

                        Toggle::make('allows_deletion')
                            ->label(__('shared-secrets::shared-secrets.form.allows_deletion.label'))
                            ->helperText(__('shared-secrets::shared-secrets.form.allows_deletion.helper'))
                            ->default(true),

                        Select::make('recipient_id')
                            ->label(__('shared-secrets::shared-secrets.form.recipient.label'))
                            ->helperText(__('shared-secrets::shared-secrets.form.recipient.helper'))
                            ->placeholder(__('shared-secrets::shared-secrets.form.recipient.placeholder'))
                            ->searchable()
                            ->options(fn(): array => Users::options(static::plugin()?->getRecipientQueryModifier()))
                            ->getSearchResultsUsing(fn(string $search): array => Users::search(
                                $search,
                                static::plugin()?->getRecipientQueryModifier()
                            ))
                            ->getOptionLabelUsing(fn(mixed $value): ?string => static::recipientLabel($value))
                            ->visible(fn(): bool => static::plugin()?->hasRecipients() ?? false)
                            ->columnSpanFull()
                    ])
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('shared-secrets::shared-secrets.link.heading'))
                ->description(fn(): string => $this->createdSecretDescription())
                ->icon(Heroicon::OutlinedCheckCircle)
                ->iconColor('success')
                ->visible(fn(): bool => $this->createdLink() !== null)
                ->schema([
                    TextEntry::make('createdLink')
                        ->hiddenLabel()
                        ->state(fn(): ?string => $this->createdLink())
                        ->fontFamily(FontFamily::Mono)
                        ->copyable()
                        ->copyMessage(__('shared-secrets::shared-secrets.link.copied')),

                    Actions::make([
                        Action::make('copyCreatedLink')
                            ->label(__('shared-secrets::shared-secrets.link.copy'))
                            ->icon(Heroicon::OutlinedClipboardDocument)
                            ->alpineClickHandler(fn(): string => static::copyToClipboardJs((string)$this->createdLink()))
                    ])
                ]),

            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('create')
                ->footer([
                    Actions::make([
                        Action::make('create')
                            ->label(__('shared-secrets::shared-secrets.form.submit'))
                            ->icon(Heroicon::OutlinedPaperAirplane)
                            ->submit('create')
                    ])
                ]),

            EmbeddedTable::make()
        ]);
    }

    public function create(): void
    {
        $plugin = static::plugin();
        $user = Filament::auth()->user();

        abort_unless($plugin instanceof SharedSecretsPlugin && $user !== null && $plugin->isAuthorized($user), 403);

        $data = $this->formSchema()->getState();
        $recipient = null;

        if ($plugin->hasRecipients() && filled($data['recipient_id'] ?? null)) {
            $recipient = Users::find(Cast::key($data['recipient_id']), $plugin->getRecipientQueryModifier());

            if (! $recipient instanceof Model) {
                throw ValidationException::withMessages([
                    'data.recipient_id' => __('shared-secrets::shared-secrets.form.recipient.invalid')
                ]);
            }
        }

        $secret = app(CreateSharedSecret::class)->handle(
            creator: $user,
            panelId: Filament::getCurrentOrDefaultPanel()?->getId() ?? '',
            content: Cast::string($data['content'] ?? null),
            expiresInMinutes: Cast::int($data['expires_in'] ?? null),
            maxViews: Cast::int($data['max_views'] ?? null, 1),
            note: Cast::nullableString($data['note'] ?? null),
            passphrase: Cast::nullableString($data['passphrase'] ?? null),
            requiresRetrievalStep: (bool)($data['requires_retrieval_step'] ?? true),
            allowsDeletion: (bool)($data['allows_deletion'] ?? true),
            recipient: $recipient,
        );

        $this->createdSecretId = $secret->id;

        // Refilling drops the content and the passphrase from the Livewire state.
        $this->formSchema()->fill();

        Notification::make()
            ->success()
            ->title(__('shared-secrets::shared-secrets.page.created'))
            ->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('shared-secrets::shared-secrets.table.heading'))
            ->query(fn(): Builder => $this->secretsQuery())
            ->columns([
                TextColumn::make('note')
                    ->label(__('shared-secrets::shared-secrets.table.note'))
                    ->placeholder('—')
                    ->limit(40)
                    ->sortable(false),

                TextColumn::make('creator')
                    ->label(__('shared-secrets::shared-secrets.table.creator'))
                    ->state(fn(SharedSecret $record): ?string => $record->creator === null ? null : Users::label($record->creator))
                    ->placeholder('—')
                    ->visible(fn(): bool => static::plugin()?->canViewAllSecrets() ?? false)
                    ->sortable(false),

                TextColumn::make('recipient')
                    ->label(__('shared-secrets::shared-secrets.table.recipient'))
                    ->state(fn(SharedSecret $record): string => $record->recipient === null
                        ? __('shared-secrets::shared-secrets.table.anyone')
                        : Users::label($record->recipient))
                    ->sortable(false),

                TextColumn::make('status')
                    ->label(__('shared-secrets::shared-secrets.table.status'))
                    ->state(fn(SharedSecret $record): SharedSecretStatus => $record->status())
                    ->badge()
                    ->sortable(false),

                TextColumn::make('views')
                    ->label(__('shared-secrets::shared-secrets.table.views'))
                    ->state(fn(SharedSecret $record): string => "{$record->views_count} / {$record->max_views}")
                    ->sortable(false),

                TextColumn::make('expires_at')
                    ->label(__('shared-secrets::shared-secrets.table.expires_at'))
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('shared-secrets::shared-secrets.table.created_at'))
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('copyLink')
                    ->label(__('shared-secrets::shared-secrets.link.copy'))
                    ->tooltip(__('shared-secrets::shared-secrets.link.copy'))
                    ->icon(Heroicon::OutlinedClipboardDocument)
                    ->iconButton()
                    ->visible(fn(SharedSecret $record): bool => $this->canCopyLink($record))
                    ->alpineClickHandler(fn(SharedSecret $record): string => static::copyToClipboardJs((string)SecretUrl::for($record))),

                Action::make('events')
                    ->label(__('shared-secrets::shared-secrets.events.action'))
                    ->tooltip(__('shared-secrets::shared-secrets.events.action'))
                    ->icon(Heroicon::OutlinedClock)
                    ->iconButton()
                    ->slideOver()
                    ->modalHeading(__('shared-secrets::shared-secrets.events.heading'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('shared-secrets::shared-secrets.events.close'))
                    ->schema([
                        RepeatableEntry::make('events')
                            ->hiddenLabel()
                            ->placeholder(__('shared-secrets::shared-secrets.events.empty'))
                            ->state(fn(SharedSecret $record): Collection => $record->events()
                                ->with('user')
                                ->latest('created_at')
                                ->latest('id')
                                ->get())
                            ->table([
                                TableColumn::make(__('shared-secrets::shared-secrets.events.created_at')),
                                TableColumn::make(__('shared-secrets::shared-secrets.events.type')),
                                TableColumn::make(__('shared-secrets::shared-secrets.events.user')),
                                TableColumn::make(__('shared-secrets::shared-secrets.events.ip_address')),
                                TableColumn::make(__('shared-secrets::shared-secrets.events.user_agent'))
                            ])
                            ->schema([
                                TextEntry::make('created_at')->dateTime('d/m/Y H:i:s'),
                                TextEntry::make('type')->badge(),
                                TextEntry::make('user')
                                    ->state(fn(SharedSecretEvent $record): string => $record->user === null
                                        ? __('shared-secrets::shared-secrets.events.guest')
                                        : Users::label($record->user)),
                                TextEntry::make('ip_address')->placeholder('—'),
                                TextEntry::make('user_agent')->placeholder('—')
                            ])
                    ]),

                Action::make('revoke')
                    ->label(__('shared-secrets::shared-secrets.table.revoke.label'))
                    ->tooltip(__('shared-secrets::shared-secrets.table.revoke.label'))
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->iconButton()
                    ->requiresConfirmation()
                    ->modalDescription(__('shared-secrets::shared-secrets.table.revoke.description'))
                    ->visible(fn(SharedSecret $record): bool => $record->isAvailable() && $this->isSender($record))
                    ->action(function(SharedSecret $record): void {
                        if (! $this->revoke($record)) {
                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title(__('shared-secrets::shared-secrets.table.revoke.done'))
                            ->send();
                    }),

                DeleteAction::make()
                    ->iconButton()
                    ->tooltip(__('filament-actions::delete.single.label'))
                    ->visible(fn(SharedSecret $record): bool => $this->isSender($record))
            ])
            ->emptyStateHeading(__('shared-secrets::shared-secrets.table.empty.heading'))
            ->emptyStateDescription(__('shared-secrets::shared-secrets.table.empty.description'))
            ->emptyStateIcon(Heroicon::OutlinedKey);
    }

    /**
     * @return array<int, string>
     */
    public static function expiryOptions(): array
    {
        $options = [];

        foreach (config()->array('shared-secrets.expiry.options', [10080]) as $minutes) {
            $minutes = Cast::int($minutes);

            if ($minutes < 1) {
                continue;
            }

            [$unit, $count] = match (true) {
                $minutes % 1440 === 0 => ['days', intdiv($minutes, 1440)],
                $minutes % 60 === 0   => ['hours', intdiv($minutes, 60)],
                default               => ['minutes', $minutes]
            };

            $options[$minutes] = trans_choice("shared-secrets::shared-secrets.form.expires_in.{$unit}", $count, ['count' => $count]);
        }

        return $options;
    }

    /**
     * One click on a compact row of buttons while the maximum is small; a
     * numeric input once a host raises it past what fits in a row.
     */
    protected static function maxViewsField(): Field
    {
        $max = max(1, config()->integer('shared-secrets.max_views.max', 10));
        $default = min($max, config()->integer('shared-secrets.max_views.default', 3));

        $field = $max <= 10
            ? ToggleButtons::make('max_views')
                ->options(array_combine(range(1, $max), array_map(strval(...), range(1, $max))))
                ->grouped()
            : TextInput::make('max_views')
                ->integer()
                ->minValue(1)
                ->maxValue($max);

        return $field
            ->label(__('shared-secrets::shared-secrets.form.max_views.label'))
            ->helperText(__('shared-secrets::shared-secrets.form.max_views.helper'))
            ->default($default)
            ->required();
    }

    protected static function plugin(): ?SharedSecretsPlugin
    {
        return SharedSecretsPlugin::get();
    }

    protected static function recipientLabel(mixed $value): ?string
    {
        $user = Users::find(Cast::key($value), static::plugin()?->getRecipientQueryModifier());

        return $user instanceof Model ? Users::label($user) : null;
    }

    /**
     * The value is JSON-encoded, so it is safe to embed in the expression.
     */
    protected static function copyToClipboardJs(string $value): string
    {
        return 'window.navigator.clipboard.writeText(' . Js::from($value) . '); '
            . '$tooltip(' . Js::from(__('shared-secrets::shared-secrets.link.copied')) . ', { theme: $store.theme })';
    }

    /**
     * Senders only ever see their own secrets, unless the plugin lets the
     * current user see everyone's.
     *
     * @return Builder<SharedSecret>
     */
    protected function secretsQuery(): Builder
    {
        $seesAll = static::plugin()?->canViewAllSecrets() ?? false;

        // The sender is only shown, and so only loaded, when the list spans several senders.
        return SharedSecret::query()
            ->with($seesAll ? ['creator', 'recipient'] : ['recipient'])
            ->unless($seesAll, fn(Builder $query) => $query->where('creator_id', Filament::auth()->id()));
    }

    /**
     * The link is as good as the secret for an untargeted one, so only its
     * sender gets it, even when someone else is allowed to see the row.
     */
    protected function canCopyLink(SharedSecret $secret): bool
    {
        return $secret->isAvailable() && $this->isSender($secret);
    }

    /**
     * Seeing everyone's secrets is read-only: only its sender can copy the
     * link of a secret, revoke it or delete it.
     */
    protected function isSender(SharedSecret $secret): bool
    {
        return $secret->isFrom(Cast::key(Filament::auth()->id()));
    }

    /**
     * The row is locked and read again, so a secret a reader closed in the
     * meantime keeps the reason it was closed for. Returns whether it was
     * revoked.
     */
    protected function revoke(SharedSecret $secret): bool
    {
        return DB::transaction(function() use ($secret): bool {
            $locked = SharedSecret::query()->lockForUpdate()->find($secret->getKey());

            if (! $locked instanceof SharedSecret || ! $locked->isAvailable()) {
                return false;
            }

            app(CloseSharedSecret::class)->handle($locked, SharedSecretStatus::Revoked, SharedSecretEventType::Revoked, Visitor::current());

            return true;
        });
    }

    protected function createdSecret(): ?SharedSecret
    {
        if ($this->createdSecretId === null) {
            return null;
        }

        return once(fn(): ?SharedSecret => SharedSecret::query()
            ->with('recipient')
            ->whereKey($this->createdSecretId)
            ->where('creator_id', Filament::auth()->id())
            ->first());
    }

    protected function createdLink(): ?string
    {
        $secret = $this->createdSecret();

        return $secret instanceof SharedSecret && $secret->isAvailable() ? SecretUrl::for($secret) : null;
    }

    protected function createdSecretDescription(): string
    {
        $recipient = $this->createdSecret()?->recipient;

        return $recipient === null
            ? __('shared-secrets::shared-secrets.link.description.anyone')
            : __('shared-secrets::shared-secrets.link.description.recipient', ['name' => Users::label($recipient)]);
    }

    protected function formSchema(): Schema
    {
        return $this->getSchema('form') ?? throw new LogicException('The shared secrets form schema is missing.');
    }
}
