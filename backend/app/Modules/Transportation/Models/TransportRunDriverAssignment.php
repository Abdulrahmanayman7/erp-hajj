<?php

namespace App\Modules\Transportation\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\Employees\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Run driver history row (ADR-0018 T4/T7): exactly one of
 * driver_employee_id / external_driver_id.
 *
 * @property int $id
 * @property int $season_id
 * @property int $vehicle_run_id
 * @property int|null $driver_employee_id
 * @property int|null $external_driver_id
 * @property Carbon $assigned_at
 * @property Carbon|null $ended_at
 * @property string|null $reason
 */
class TransportRunDriverAssignment extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'season_id',
        'vehicle_run_id',
        'driver_employee_id',
        'external_driver_id',
        'assigned_at',
        'assigned_by',
        'ended_at',
        'ended_by',
        'reason',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['current_flag'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(TransportVehicleRun::class, 'vehicle_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'driver_employee_id');
    }

    public function externalDriver(): BelongsTo
    {
        return $this->belongsTo(TransportExternalDriver::class, 'external_driver_id');
    }

    public function driverType(): string
    {
        return $this->driver_employee_id !== null ? 'employee' : 'external';
    }
}
