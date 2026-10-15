<?php

namespace App\Modules\Transportation\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\Employees\Models\Employee;
use App\Modules\SeasonPilgrims\Contracts\SeasonOwned;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\Transportation\Enums\ServiceLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Vehicle's participation in one Season (ADR-0018 T3/T4/T5).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $season_id
 * @property int $transport_vehicle_id
 * @property int $operational_capacity
 * @property ServiceLevel $service_level
 * @property int|null $default_driver_employee_id
 * @property int|null $default_external_driver_id
 * @property int|null $default_supervisor_employee_id
 * @property bool $is_active
 * @property string|null $notes
 * @property-read TransportVehicle $vehicle
 */
class TransportSeasonVehicle extends Model implements SeasonOwned, TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'season_id',
        'transport_vehicle_id',
        'operational_capacity',
        'service_level',
        'default_driver_employee_id',
        'default_external_driver_id',
        'default_supervisor_employee_id',
        'is_active',
        'notes',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'operational_capacity' => 'integer',
            'service_level' => ServiceLevel::class,
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

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(TransportVehicle::class, 'transport_vehicle_id');
    }

    public function defaultDriverEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'default_driver_employee_id');
    }

    public function defaultExternalDriver(): BelongsTo
    {
        return $this->belongsTo(TransportExternalDriver::class, 'default_external_driver_id');
    }

    public function defaultSupervisorEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'default_supervisor_employee_id');
    }

    public function isUsed(): bool
    {
        return TransportRunVehicleAssignment::query()->where('transport_season_vehicle_id', $this->id)->exists();
    }
}
