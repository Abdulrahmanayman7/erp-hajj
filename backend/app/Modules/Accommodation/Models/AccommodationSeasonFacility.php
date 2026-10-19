<?php

namespace App\Modules\Accommodation\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\SeasonPilgrims\Contracts\SeasonOwned;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $season_id
 * @property int $facility_id
 * @property string|null $audience
 * @property bool $is_active
 */
class AccommodationSeasonFacility extends Model implements SeasonOwned, TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'season_id',
        'facility_id',
        'audience',
        'is_active',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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

    public function facility(): BelongsTo
    {
        return $this->belongsTo(AccommodationFacility::class, 'facility_id');
    }

    public function seasonUnits(): HasMany
    {
        return $this->hasMany(AccommodationSeasonUnit::class, 'season_facility_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
