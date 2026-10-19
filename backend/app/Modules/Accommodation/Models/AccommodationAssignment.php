<?php

namespace App\Modules\Accommodation\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\SeasonPilgrims\Contracts\SeasonOwned;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Assignment stay. Sprint 3 Gate B CHECK acc_asg_booking_ck allows draft,
 * confirmed (optional ops), cancelled (pre-check-in), and completed/checked_out.
 * Ops APIs are Gate C — this model only reflects schema.
 *
 * Relocation chains: destination.relocated_from_assignment_id → source.id
 * (same tenant + season). Source rows are retained (RESTRICT).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $season_id
 * @property int $season_pilgrim_id
 * @property int $season_unit_id
 * @property int $unit_id
 * @property int|null $season_position_id
 * @property string $booking_status
 * @property string|null $operational_status
 * @property Carbon $starts_at
 * @property Carbon $original_ends_at
 * @property Carbon $effective_ends_at
 * @property Carbon|null $actual_departed_at
 * @property string|null $checkout_source
 * @property int|null $relocated_from_assignment_id
 */
class AccommodationAssignment extends Model implements SeasonOwned, TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'season_id',
        'season_pilgrim_id',
        'season_unit_id',
        'unit_id',
        'season_position_id',
        'booking_status',
        'operational_status',
        'starts_at',
        'original_ends_at',
        'effective_ends_at',
        'actual_departed_at',
        'family_group_id',
        'checkout_source',
        'relocated_from_assignment_id',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'original_ends_at' => 'datetime',
            'effective_ends_at' => 'datetime',
            'actual_departed_at' => 'datetime',
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

    public function seasonPilgrim(): BelongsTo
    {
        return $this->belongsTo(SeasonPilgrim::class, 'season_pilgrim_id');
    }

    public function seasonUnit(): BelongsTo
    {
        return $this->belongsTo(AccommodationSeasonUnit::class, 'season_unit_id');
    }

    public function seasonPosition(): BelongsTo
    {
        return $this->belongsTo(AccommodationSeasonPosition::class, 'season_position_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function relocatedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'relocated_from_assignment_id');
    }
}
