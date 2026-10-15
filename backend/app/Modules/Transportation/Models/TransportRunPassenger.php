<?php

namespace App\Modules\Transportation\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\SeasonPilgrims\Contracts\SeasonOwned;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\Transportation\Enums\AssignmentSource;
use App\Modules\Transportation\Enums\EntitlementStatus;
use App\Modules\Transportation\Enums\PassengerStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Materialized manifest row: one SeasonPilgrim on one Run (ADR-0018
 * T5/T8/T9). Never deleted.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $season_id
 * @property int $vehicle_run_id
 * @property int $season_pilgrim_id
 * @property PassengerStatus $status
 * @property AssignmentSource $assignment_source
 * @property int|null $source_group_id
 * @property string|null $source_group_label
 * @property int|null $program_id_at_assignment
 * @property string|null $program_code_at_assignment
 * @property int|null $entitlement_id_at_assignment
 * @property string|null $required_vehicle_class_at_assignment
 * @property string|null $required_service_level_at_assignment
 * @property int $season_vehicle_id_at_assignment
 * @property string|null $vehicle_class_at_assignment
 * @property string|null $service_level_at_assignment
 * @property EntitlementStatus $entitlement_status_at_assignment
 * @property bool $override_at_assignment
 * @property string|null $override_reason_at_assignment
 * @property bool $entitlement_override
 * @property string|null $entitlement_override_reason
 * @property int|null $entitlement_overridden_by
 * @property Carbon|null $entitlement_overridden_at
 * @property int|null $assigned_by
 * @property Carbon $assigned_at
 * @property Carbon|null $boarded_at
 * @property Carbon|null $arrived_at
 * @property Carbon|null $no_show_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property-read TransportVehicleRun $run
 * @property-read SeasonPilgrim $seasonPilgrim
 */
class TransportRunPassenger extends Model implements SeasonOwned, TenantOwned
{
    use UsesTenantScope;

    /**
     * T9 assignment decision; written once on insert.
     */
    public const ASSIGNMENT_CONTEXT = [
        'season_pilgrim_id',
        'vehicle_run_id',
        'assignment_source',
        'source_group_id',
        'source_group_label',
        'program_id_at_assignment',
        'program_code_at_assignment',
        'entitlement_id_at_assignment',
        'required_vehicle_class_at_assignment',
        'required_service_level_at_assignment',
        'season_vehicle_id_at_assignment',
        'vehicle_class_at_assignment',
        'service_level_at_assignment',
        'entitlement_status_at_assignment',
        'override_at_assignment',
        'override_reason_at_assignment',
        'assigned_at',
        'assigned_by',
    ];

    protected $fillable = [
        'season_id',
        'vehicle_run_id',
        'season_pilgrim_id',
        'status',
        'assignment_source',
        'source_group_id',
        'source_group_label',
        'program_id_at_assignment',
        'program_code_at_assignment',
        'entitlement_id_at_assignment',
        'required_vehicle_class_at_assignment',
        'required_service_level_at_assignment',
        'season_vehicle_id_at_assignment',
        'vehicle_class_at_assignment',
        'service_level_at_assignment',
        'entitlement_status_at_assignment',
        'override_at_assignment',
        'override_reason_at_assignment',
        'entitlement_override',
        'entitlement_override_reason',
        'entitlement_overridden_by',
        'entitlement_overridden_at',
        'assigned_at',
        'assigned_by',
        'boarded_at',
        'arrived_at',
        'no_show_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['occupying_flag'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PassengerStatus::class,
            'assignment_source' => AssignmentSource::class,
            'entitlement_status_at_assignment' => EntitlementStatus::class,
            'override_at_assignment' => 'boolean',
            'entitlement_override' => 'boolean',
            'entitlement_overridden_at' => 'datetime',
            'assigned_at' => 'datetime',
            'boarded_at' => 'datetime',
            'arrived_at' => 'datetime',
            'no_show_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $passenger): void {
            if ($passenger->isDirty(self::ASSIGNMENT_CONTEXT)) {
                throw new LogicException('The passenger assignment context is immutable.');
            }
        });
    }

    public function owningSeasonId(): int
    {
        return (int) $this->season_id;
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(TransportVehicleRun::class, 'vehicle_run_id');
    }

    public function seasonPilgrim(): BelongsTo
    {
        return $this->belongsTo(SeasonPilgrim::class);
    }
}
