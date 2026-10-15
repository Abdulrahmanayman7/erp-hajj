<?php

namespace App\Modules\Transportation\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\SeasonPilgrims\Contracts\SeasonOwned;
use App\Modules\Transportation\Enums\TimelineEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Append-only operational timeline entry (ADR-0018 T6). Updates and
 * deletes are rejected; corrections append a superseding row.
 *
 * @property int $id
 * @property int $season_id
 * @property int $transport_movement_id
 * @property int|null $vehicle_run_id
 * @property int|null $run_passenger_id
 * @property TimelineEventType $event_type
 * @property Carbon $occurred_at
 * @property Carbon $recorded_at
 * @property int|null $actor_user_id
 * @property string|null $reason
 * @property array<string, mixed>|null $context
 * @property int|null $supersedes_event_id
 * @property string|null $correlation_id
 */
class TransportTimelineEvent extends Model implements SeasonOwned, TenantOwned
{
    use UsesTenantScope;

    public const UPDATED_AT = null;

    protected $fillable = [
        'season_id',
        'transport_movement_id',
        'vehicle_run_id',
        'run_passenger_id',
        'event_type',
        'occurred_at',
        'recorded_at',
        'actor_user_id',
        'reason',
        'context',
        'supersedes_event_id',
        'correlation_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => TimelineEventType::class,
            'occurred_at' => 'datetime',
            'recorded_at' => 'datetime',
            'context' => 'array',
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

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_event_id');
    }

    public function supersededBy(): HasOne
    {
        return $this->hasOne(self::class, 'supersedes_event_id');
    }
}
