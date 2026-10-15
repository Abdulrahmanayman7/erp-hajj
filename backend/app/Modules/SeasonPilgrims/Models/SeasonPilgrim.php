<?php

namespace App\Modules\SeasonPilgrims\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\SeasonPilgrims\Contracts\SeasonOwned;
use App\Modules\SeasonPilgrims\Enums\SeasonPilgrimStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * One pilgrim's participation in one season (F3, F5, F10). Holds no
 * transportation/accommodation state and no supervisor_id (F27).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $season_id
 * @property int $pilgrim_id
 * @property string $reference_number
 * @property SeasonPilgrimStatus $status
 * @property int|null $season_program_id
 * @property int|null $season_pilgrim_group_id
 * @property int|null $season_family_group_id
 * @property bool $is_family_primary
 * @property Carbon|null $activated_at
 */
class SeasonPilgrim extends Model implements SeasonOwned, TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'season_id',
        'pilgrim_id',
        'reference_number',
        'status',
        'season_program_id',
        'season_pilgrim_group_id',
        'season_family_group_id',
        'is_family_primary',
        'registered_at',
        'registered_by',
        'activated_at',
        'activated_by',
        'completed_at',
        'completed_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['family_primary_flag'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SeasonPilgrimStatus::class,
            'is_family_primary' => 'boolean',
            'registered_at' => 'datetime',
            'activated_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    public function pilgrim(): BelongsTo
    {
        return $this->belongsTo(Pilgrim::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(SeasonProgram::class, 'season_program_id');
    }

    public function pilgrimGroup(): BelongsTo
    {
        return $this->belongsTo(SeasonPilgrimGroup::class, 'season_pilgrim_group_id');
    }

    public function familyGroup(): BelongsTo
    {
        return $this->belongsTo(SeasonFamilyGroup::class, 'season_family_group_id');
    }

    public function snapshot(): HasOne
    {
        return $this->hasOne(SeasonPilgrimIdentitySnapshot::class);
    }

    public function programChanges(): HasMany
    {
        return $this->hasMany(SeasonPilgrimProgramChange::class);
    }
}
