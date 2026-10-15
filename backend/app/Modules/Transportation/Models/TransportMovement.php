<?php

namespace App\Modules\Transportation\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\SeasonPilgrims\Contracts\SeasonOwned;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\Transportation\Enums\MovementStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Transportation Movement (ADR-0018 T1/T6). Status is persisted.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $season_id
 * @property string $movement_number
 * @property int $origin_point_id
 * @property int $destination_point_id
 * @property Carbon $planned_departure_at
 * @property Carbon $planned_arrival_at
 * @property MovementStatus $status
 * @property Carbon|null $planned_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property string|null $notes
 */
class TransportMovement extends Model implements SeasonOwned, TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'season_id',
        'movement_number',
        'origin_point_id',
        'destination_point_id',
        'planned_departure_at',
        'planned_arrival_at',
        'status',
        'planned_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'notes',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MovementStatus::class,
            'planned_departure_at' => 'datetime',
            'planned_arrival_at' => 'datetime',
            'planned_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function owningSeasonId(): int
    {
        return (int) $this->season_id;
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(TransportPoint::class, 'origin_point_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(TransportPoint::class, 'destination_point_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(TransportVehicleRun::class)->orderBy('run_number');
    }
}
