<?php

namespace App\Modules\Transportation\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\SeasonPilgrims\Contracts\SeasonOwned;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\Transportation\Enums\PassengerStatus;
use App\Modules\Transportation\Enums\RunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * One vehicle's execution inside a Movement (ADR-0018 T1/T6/T7).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $season_id
 * @property int $transport_movement_id
 * @property int $run_number
 * @property RunStatus $status
 * @property int|null $capacity_override
 * @property Carbon|null $boarding_started_at
 * @property Carbon|null $departed_at
 * @property Carbon|null $arrived_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property string|null $notes
 * @property-read TransportMovement $movement
 */
class TransportVehicleRun extends Model implements SeasonOwned, TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'season_id',
        'transport_movement_id',
        'run_number',
        'status',
        'capacity_override',
        'boarding_started_at',
        'departed_at',
        'arrived_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'notes',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RunStatus::class,
            'run_number' => 'integer',
            'capacity_override' => 'integer',
            'boarding_started_at' => 'datetime',
            'departed_at' => 'datetime',
            'arrived_at' => 'datetime',
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

    public function movement(): BelongsTo
    {
        return $this->belongsTo(TransportMovement::class, 'transport_movement_id');
    }

    public function vehicleAssignments(): HasMany
    {
        return $this->hasMany(TransportRunVehicleAssignment::class, 'vehicle_run_id')->orderBy('id');
    }

    public function driverAssignments(): HasMany
    {
        return $this->hasMany(TransportRunDriverAssignment::class, 'vehicle_run_id')->orderBy('id');
    }

    public function supervisorAssignments(): HasMany
    {
        return $this->hasMany(TransportRunSupervisorAssignment::class, 'vehicle_run_id')->orderBy('id');
    }

    public function currentVehicleAssignment(): HasOne
    {
        return $this->hasOne(TransportRunVehicleAssignment::class, 'vehicle_run_id')->whereNull('ended_at');
    }

    public function currentDriverAssignment(): HasOne
    {
        return $this->hasOne(TransportRunDriverAssignment::class, 'vehicle_run_id')->whereNull('ended_at');
    }

    public function currentSupervisorAssignment(): HasOne
    {
        return $this->hasOne(TransportRunSupervisorAssignment::class, 'vehicle_run_id')->whereNull('ended_at');
    }

    public function passengers(): HasMany
    {
        return $this->hasMany(TransportRunPassenger::class, 'vehicle_run_id')->orderBy('id');
    }

    public function occupiedCount(): int
    {
        return TransportRunPassenger::query()
            ->where('vehicle_run_id', $this->id)
            ->whereIn('status', PassengerStatus::occupyingValues())
            ->count();
    }

    /**
     * Override, else the current SeasonVehicle capacity (T5).
     */
    public function effectiveCapacity(): ?int
    {
        if ($this->capacity_override !== null) {
            return $this->capacity_override;
        }

        $assignment = $this->currentVehicleAssignment()->with('seasonVehicle')->first();

        return $assignment?->seasonVehicle?->operational_capacity;
    }
}
