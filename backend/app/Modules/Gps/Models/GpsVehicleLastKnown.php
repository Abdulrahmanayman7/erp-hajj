<?php

namespace App\Modules\Gps\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\SeasonPilgrims\Contracts\SeasonOwned;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Latest known GPS fix for a season vehicle.
 *
 * History retention is BLOCKED (U04 / G5) — this table is last-known only.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $season_id
 * @property int $transport_season_vehicle_id
 * @property string $latitude
 * @property string $longitude
 * @property Carbon $recorded_at
 * @property string|null $device_id
 */
class GpsVehicleLastKnown extends Model implements SeasonOwned, TenantOwned
{
    use UsesTenantScope;

    protected $table = 'gps_vehicle_last_known';

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
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

    public function seasonVehicle(): BelongsTo
    {
        return $this->belongsTo(TransportSeasonVehicle::class, 'transport_season_vehicle_id');
    }
}
