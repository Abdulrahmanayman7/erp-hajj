<?php

namespace App\Modules\Gps\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Authorized GPS device identity for one season vehicle within a tenant.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $device_id
 * @property int $transport_season_vehicle_id
 */
class GpsDeviceBinding extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $table = 'gps_device_bindings';

    /** @var list<string> */
    protected $fillable = [];

    public function seasonVehicle(): BelongsTo
    {
        return $this->belongsTo(TransportSeasonVehicle::class, 'transport_season_vehicle_id');
    }
}
