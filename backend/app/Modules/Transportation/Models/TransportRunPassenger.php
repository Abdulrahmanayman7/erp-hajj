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
 * T5/T8/T9). Never deleted. ASSIGNMENT_CONTEXT columns are immutable.
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
 * @property string|null $entitlement_vehicle_class
 * @property string|null $entitlement_service_level
 * @property int|null $season_vehicle_id_at_assignment
 * @property string|null $vehicle_class_at_assignment
 * @property string|null $service_level_at_assignment
 * @property EntitlementStatus $entitlement_status_at_assignment
 * @property bool $override_at_assignment
 * @property string|null $override_reason_at_assignment
 * @property int|null $overridden_by_at_assignment
 * @property bool $entitlement_override
 * @property string|null $entitlement_override_reason
 * @property int|null $entitlement_overridden_by
 * @property Carbon|null $entitlement_overridden_at
 * @property Carbon $assigned_at
 * @property int|null $assigned_by
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
     * Immutable assignment-time decision columns (T9). Later Program /
     * entitlement / vehicle edits and live overrides must not rewrite them.
     *
     * @var list<string>
     */
    public const ASSIGNMENT_CONTEXT = [
        'program_id_at_assignment',
        'program_code_at_assignment',
        'entitlement_id_at_assignment',
        'entitlement_vehicle_class',
        'entitlement_service_level',
        'season_vehicle_id_at_assignment',
        'vehicle_class_at_assignment',
        'service_level_at_assignment',
        'entitlement_status_at_assignment',
        'override_at_assignment',
        'override_reason_at_assignment',
        'overridden_by_at_assignment',
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
        'entitlement_vehicle_class',
        'entitlement_service_level',
        'season_vehicle_id_at_assignment',
        'vehicle_class_at_assignment',
        'service_level_at_assignment',
        'entitlement_status_at_assignment',
        'override_at_assignment',
        'override_reason_at_assignment',
        'overridden_by_at_assignment',
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
            foreach (self::ASSIGNMENT_CONTEXT as $column) {
                if ($passenger->isDirty($column)) {
                    throw new LogicException("Assignment context column {$column} is immutable.");
                }
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

    /**
     * Normalized historical decision exposed on the passenger resource.
     *
     * @return array<string, mixed>
     */
    public function assignmentContext(): array
    {
        return [
            'season_program_id' => $this->program_id_at_assignment,
            'season_program_code' => $this->program_code_at_assignment,
            'entitlement_id' => $this->entitlement_id_at_assignment,
            'required_vehicle_class' => $this->entitlement_vehicle_class,
            'required_service_level' => $this->entitlement_service_level,
            'transport_season_vehicle_id' => $this->season_vehicle_id_at_assignment,
            'vehicle_class' => $this->vehicle_class_at_assignment,
            'service_level' => $this->service_level_at_assignment,
            'entitlement_status' => $this->entitlement_status_at_assignment->value,
            'override_used' => (bool) $this->override_at_assignment,
            'override_reason' => $this->override_reason_at_assignment,
            'overridden_by' => $this->overridden_by_at_assignment,
            'assigned_by' => $this->assigned_by,
        ];
    }
}
