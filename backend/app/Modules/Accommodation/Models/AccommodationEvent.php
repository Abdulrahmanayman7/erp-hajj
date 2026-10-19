<?php

namespace App\Modules\Accommodation\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\SeasonPilgrims\Contracts\SeasonOwned;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only operational timeline (Sprint 3 Gate B foundation).
 * Updates and deletes are rejected; business writers land in Gate C.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $season_id
 * @property int $assignment_id
 * @property string $event_type
 * @property Carbon $occurred_at
 * @property Carbon $recorded_at
 * @property int|null $actor_user_id
 * @property string $source
 * @property string $idempotency_key
 * @property string|null $correlation_id
 * @property array<string, mixed>|null $meta
 */
class AccommodationEvent extends Model implements SeasonOwned, TenantOwned
{
    use UsesTenantScope;

    public const UPDATED_AT = null;

    public const TYPE_ARRIVED = 'arrived';

    public const TYPE_CHECKED_IN = 'checked_in';

    public const TYPE_CHECKED_OUT = 'checked_out';

    public const TYPE_RELOCATED = 'relocated';

    public const TYPE_CANCELLED = 'cancelled';

    protected $fillable = [
        'season_id',
        'assignment_id',
        'event_type',
        'occurred_at',
        'recorded_at',
        'actor_user_id',
        'source',
        'idempotency_key',
        'correlation_id',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'recorded_at' => 'datetime',
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): bool => false);
        static::deleting(fn (): bool => false);
    }

    public function owningSeasonId(): int
    {
        return (int) $this->season_id;
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(AccommodationAssignment::class, 'assignment_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
