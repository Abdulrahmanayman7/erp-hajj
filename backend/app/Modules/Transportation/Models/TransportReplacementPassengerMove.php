<?php

namespace App\Modules\Transportation\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\SeasonPilgrims\Contracts\SeasonOwned;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Explicit passenger move recorded during a capacity-short replacement.
 */
class TransportReplacementPassengerMove extends Model implements SeasonOwned, TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'season_id',
        'replacement_id',
        'from_run_id',
        'to_run_id',
        'season_pilgrim_id',
        'source_passenger_id',
        'target_passenger_id',
    ];

    public function owningSeasonId(): int
    {
        return (int) $this->season_id;
    }

    public function replacement(): BelongsTo
    {
        return $this->belongsTo(TransportVehicleReplacement::class, 'replacement_id');
    }
}
