<?php

namespace App\Modules\Transportation\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\SeasonPilgrims\Contracts\SeasonOwned;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\Transportation\Enums\ReplacementStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Completed atomic replacement for a breakdown (ADR-0021 D03).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $season_id
 * @property int $breakdown_id
 * @property int $vehicle_run_id
 * @property int $from_season_vehicle_id
 * @property int $to_season_vehicle_id
 * @property ReplacementStatus $status
 * @property Carbon $executed_at
 */
class TransportVehicleReplacement extends Model implements SeasonOwned, TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'season_id',
        'breakdown_id',
        'vehicle_run_id',
        'from_season_vehicle_id',
        'to_season_vehicle_id',
        'status',
        'reason',
        'passengers_retained',
        'passengers_reassigned',
        'idempotency_key',
        'executed_at',
        'executed_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReplacementStatus::class,
            'executed_at' => 'datetime',
            'passengers_retained' => 'integer',
            'passengers_reassigned' => 'integer',
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

    public function breakdown(): BelongsTo
    {
        return $this->belongsTo(TransportVehicleBreakdown::class, 'breakdown_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(TransportVehicleRun::class, 'vehicle_run_id');
    }

    public function fromSeasonVehicle(): BelongsTo
    {
        return $this->belongsTo(TransportSeasonVehicle::class, 'from_season_vehicle_id');
    }

    public function toSeasonVehicle(): BelongsTo
    {
        return $this->belongsTo(TransportSeasonVehicle::class, 'to_season_vehicle_id');
    }

    public function passengerMoves(): HasMany
    {
        return $this->hasMany(TransportReplacementPassengerMove::class, 'replacement_id');
    }
}
