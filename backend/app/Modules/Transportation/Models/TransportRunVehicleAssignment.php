<?php

namespace App\Modules\Transportation\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Run ↔ SeasonVehicle history row (ADR-0018 T7). Ended, never deleted.
 *
 * @property int $id
 * @property int $season_id
 * @property int $vehicle_run_id
 * @property int $transport_season_vehicle_id
 * @property Carbon $assigned_at
 * @property int|null $assigned_by
 * @property Carbon|null $ended_at
 * @property int|null $ended_by
 * @property string|null $reason
 * @property-read TransportSeasonVehicle $seasonVehicle
 */
class TransportRunVehicleAssignment extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'season_id',
        'vehicle_run_id',
        'transport_season_vehicle_id',
        'assigned_at',
        'assigned_by',
        'ended_at',
        'ended_by',
        'reason',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['current_flag'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(TransportVehicleRun::class, 'vehicle_run_id');
    }

    public function seasonVehicle(): BelongsTo
    {
        return $this->belongsTo(TransportSeasonVehicle::class, 'transport_season_vehicle_id');
    }
}
