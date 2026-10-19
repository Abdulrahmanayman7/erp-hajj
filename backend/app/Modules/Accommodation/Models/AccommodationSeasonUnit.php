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
 * @property int $season_facility_id
 * @property int $facility_id
 * @property int $unit_id
 * @property string $allocation_mode
 * @property int|null $capacity
 * @property string $audience
 * @property bool $is_active
 */
class AccommodationSeasonUnit extends Model implements SeasonOwned, TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'season_id',
        'season_facility_id',
        'facility_id',
        'unit_id',
        'allocation_mode',
        'capacity',
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
            'capacity' => 'integer',
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

    public function seasonFacility(): BelongsTo
    {
        return $this->belongsTo(AccommodationSeasonFacility::class, 'season_facility_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(AccommodationUnit::class, 'unit_id');
    }

    public function seasonPositions(): HasMany
    {
        return $this->hasMany(AccommodationSeasonPosition::class, 'season_unit_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
