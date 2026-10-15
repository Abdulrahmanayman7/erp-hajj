<?php

namespace App\Modules\SeasonPilgrims\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\SeasonPilgrims\Contracts\SeasonOwned;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Season-specific operational group (F26, F31). Not an OrganizationUnit.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $season_id
 * @property string $code
 * @property string $name
 */
class SeasonPilgrimGroup extends Model implements SeasonOwned, TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'season_id',
        'code',
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

    public function seasonPilgrims(): HasMany
    {
        return $this->hasMany(SeasonPilgrim::class);
    }

    public function supervisors(): HasMany
    {
        return $this->hasMany(SeasonPilgrimGroupSupervisor::class);
    }

    public function currentSupervisors(): HasMany
    {
        return $this->supervisors()->whereNull('ended_at');
    }
}
