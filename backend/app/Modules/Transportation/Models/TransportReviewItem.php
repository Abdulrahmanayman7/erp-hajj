<?php

namespace App\Modules\Transportation\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\SeasonPilgrims\Contracts\SeasonOwned;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\Transportation\Enums\ReviewSeverity;
use App\Modules\Transportation\Enums\ReviewStatus;
use App\Modules\Transportation\Enums\ReviewType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * "Needs Review" item (ADR-0018 T8/T10). Resolution never changes
 * passenger history.
 *
 * @property int $id
 * @property int $season_id
 * @property int $vehicle_run_id
 * @property int $run_passenger_id
 * @property int $season_pilgrim_id
 * @property ReviewType $review_type
 * @property ReviewSeverity $severity
 * @property ReviewStatus $status
 * @property array<string, mixed>|null $details
 * @property Carbon $raised_at
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property string|null $resolution_note
 * @property-read TransportRunPassenger $passenger
 */
class TransportReviewItem extends Model implements SeasonOwned, TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'season_id',
        'vehicle_run_id',
        'run_passenger_id',
        'season_pilgrim_id',
        'review_type',
        'severity',
        'status',
        'details',
        'raised_at',
        'resolved_at',
        'resolved_by',
        'resolution_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'review_type' => ReviewType::class,
            'severity' => ReviewSeverity::class,
            'status' => ReviewStatus::class,
            'details' => 'array',
            'raised_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function owningSeasonId(): int
    {
        return (int) $this->season_id;
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(TransportVehicleRun::class, 'vehicle_run_id');
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(TransportRunPassenger::class, 'run_passenger_id');
    }

    public function seasonPilgrim(): BelongsTo
    {
        return $this->belongsTo(SeasonPilgrim::class);
    }
}
