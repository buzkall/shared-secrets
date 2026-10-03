<?php

namespace Arzcode\SharedSecrets\Models;

use Arzcode\SharedSecrets\Data\Visitor;
use Arzcode\SharedSecrets\Database\Factories\SharedSecretFactory;
use Arzcode\SharedSecrets\Enums\SharedSecretEventType;
use Arzcode\SharedSecrets\Enums\SharedSecretStatus;
use Arzcode\SharedSecrets\Support\Users;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $panel
 * @property int|string|null $creator_id
 * @property int|string|null $recipient_id
 * @property string|null $content
 * @property string|null $note
 * @property string|null $passphrase
 * @property int $max_views
 * @property int $views_count
 * @property Carbon $expires_at
 * @property bool $requires_retrieval_step
 * @property bool $allows_deletion
 * @property Carbon|null $closed_at
 * @property SharedSecretStatus|null $closed_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $creator
 * @property-read Model|null $recipient
 */
class SharedSecret extends Model
{
    /** @use HasFactory<SharedSecretFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'panel',
        'creator_id',
        'recipient_id',
        'content',
        'note',
        'passphrase',
        'max_views',
        'views_count',
        'expires_at',
        'requires_retrieval_step',
        'allows_deletion',
        'closed_at',
        'closed_reason'
    ];
    protected $hidden = ['content', 'passphrase'];
    protected $attributes = [
        'views_count'             => 0,
        'requires_retrieval_step' => true,
        'allows_deletion'         => true
    ];

    public function getTable(): string
    {
        return config()->string('shared-secrets.table_names.secrets', 'shared_secrets');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content'                 => 'encrypted',
            'note'                    => 'encrypted',
            'max_views'               => 'integer',
            'views_count'             => 'integer',
            'expires_at'              => 'datetime',
            'requires_retrieval_step' => 'boolean',
            'allows_deletion'         => 'boolean',
            'closed_at'               => 'datetime',
            'closed_reason'           => SharedSecretStatus::class
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Users::model(), 'creator_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Users::model(), 'recipient_id');
    }

    /**
     * @return HasMany<SharedSecretEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(SharedSecretEvent::class);
    }

    /**
     * Open secrets whose lifetime has run out and still hold their payload.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->whereNull('closed_at')->where('expires_at', '<=', now());
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function closedBefore(Builder $query, Carbon $moment): void
    {
        $query->whereNotNull('closed_at')->where('closed_at', '<', $moment);
    }

    public function status(): SharedSecretStatus
    {
        if ($this->closed_reason instanceof SharedSecretStatus) {
            return $this->closed_reason;
        }

        return $this->expires_at->isPast() ? SharedSecretStatus::Expired : SharedSecretStatus::Active;
    }

    public function isAvailable(): bool
    {
        return $this->status() === SharedSecretStatus::Active;
    }

    public function hasPassphrase(): bool
    {
        return filled($this->passphrase);
    }

    public function isTargeted(): bool
    {
        return $this->recipient_id !== null;
    }

    public function isFor(int|string|null $userId): bool
    {
        return $userId !== null && (string)$this->recipient_id === (string)$userId;
    }

    public function isFrom(int|string|null $userId): bool
    {
        return $userId !== null && (string)$this->creator_id === (string)$userId;
    }

    public function recordEvent(SharedSecretEventType $type, ?Visitor $visitor = null): SharedSecretEvent
    {
        return $this->events()->create([
            'type'       => $type,
            'user_id'    => $visitor?->userId(),
            'ip_address' => $visitor?->ipAddress,
            'user_agent' => $visitor?->userAgent
        ]);
    }

    protected static function newFactory(): SharedSecretFactory
    {
        return SharedSecretFactory::new();
    }
}
