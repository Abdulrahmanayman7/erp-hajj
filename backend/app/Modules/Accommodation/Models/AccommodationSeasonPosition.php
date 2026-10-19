<?php

namespace App\Modules\Accommodation\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\SeasonPilgrims\Contracts\SeasonOwned;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $season_id
 * @property int $season_unit_id
 * @property int $unit_id
 * @property int $position_id
 * @property bool $is_active
 */
class AccommodationSeasonPosition extends Model implements SeasonOwned, TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'season_id',
        'season_unit_id',
        'unit_id',
        'position_id',
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

    public function seasonUnit(): BelongsTo
    {
        return $this->belongsTo(AccommodationSeasonUnit::class, 'season_unit_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(AccommodationPosition::class, 'position_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
