<?php

namespace App\Modules\SeasonPilgrims\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\SeasonPilgrims\Contracts\SeasonOwned;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Season-specific family group (F8, F9). Members are SeasonPilgrims.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $season_id
 * @property string $name
 */
class SeasonFamilyGroup extends Model implements SeasonOwned, TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'season_id',
        'name',
        'notes',
        'created_by',
    ];

    public function owningSeasonId(): int
    {
        return (int) $this->season_id;
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(SeasonPilgrim::class);
    }

    public function primaryMember(): HasOne
    {
        return $this->hasOne(SeasonPilgrim::class)->where('is_family_primary', true);
    }
}
