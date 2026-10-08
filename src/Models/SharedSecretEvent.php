<?php

namespace Arzcode\SharedSecrets\Models;

use Arzcode\SharedSecrets\Database\Factories\SharedSecretEventFactory;
use Arzcode\SharedSecrets\Enums\SharedSecretEventType;
use Arzcode\SharedSecrets\Support\Users;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $shared_secret_id
 * @property SharedSecretEventType $type
 * @property int|string|null $user_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property CarbonInterface|null $created_at
 * @property-read Model|null $user
 */
class SharedSecretEvent extends Model
{
    /** @use HasFactory<SharedSecretEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'shared_secret_id',
        'type',
        'user_id',
        'ip_address',
        'user_agent'
    ];

    public function getTable(): string
    {
        return config()->string('shared-secrets.table_names.events', 'shared_secret_events');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SharedSecretEventType::class
        ];
    }

    /**
     * @return BelongsTo<SharedSecret, $this>
     */
    public function secret(): BelongsTo
    {
        return $this->belongsTo(SharedSecret::class, 'shared_secret_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::model(), 'user_id');
    }

    protected static function newFactory(): SharedSecretEventFactory
    {
        return SharedSecretEventFactory::new();
    }
}
