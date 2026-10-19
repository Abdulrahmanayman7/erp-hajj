<?php

namespace App\Modules\Accommodation\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\SeasonPilgrims\Contracts\SeasonOwned;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Occupancy lock anchor only. Unique per tenant + season + SeasonPilgrim.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $season_id
 * @property int $season_pilgrim_id
 */
class AccommodationPilgrimLock extends Model implements SeasonOwned, TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'season_id',
        'season_pilgrim_id',
    ];

    public function owningSeasonId(): int
    {
        return (int) $this->season_id;
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function seasonPilgrim(): BelongsTo
    {
        return $this->belongsTo(SeasonPilgrim::class, 'season_pilgrim_id');
    }
}
