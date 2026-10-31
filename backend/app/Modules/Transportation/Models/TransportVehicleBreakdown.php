<?php

namespace App\Modules\Transportation\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\SeasonPilgrims\Contracts\SeasonOwned;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\Transportation\Enums\BreakdownStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Operational fault on a Run's current SeasonVehicle (ADR-0021 D03).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $season_id
 * @property int $vehicle_run_id
 * @property int $transport_season_vehicle_id
 * @property BreakdownStatus $status
 * @property Carbon $occurred_at
 * @property string $reason
 * @property string|null $notes
 * @property int $reported_by
 * @property int|null $replacement_id
 */
class TransportVehicleBreakdown extends Model implements SeasonOwned, TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'season_id',
        'vehicle_run_id',
        'transport_season_vehicle_id',
        'status',
        'occurred_at',
        'reason',
        'notes',
        'reported_by',
        'replacement_id',
        'closed_at',
        'closed_by',
        'closure_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BreakdownStatus::class,
            'occurred_at' => 'datetime',
            'closed_at' => 'datetime',
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

    public function run(): BelongsTo
    {
        return $this->belongsTo(TransportVehicleRun::class, 'vehicle_run_id');
    }

    public function seasonVehicle(): BelongsTo
    {
        return $this->belongsTo(TransportSeasonVehicle::class, 'transport_season_vehicle_id');
    }

    public function replacement(): BelongsTo
    {
        return $this->belongsTo(TransportVehicleReplacement::class, 'replacement_id');
    }
}
