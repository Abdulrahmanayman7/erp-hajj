<?php

namespace App\Modules\Transportation\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\Employees\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Run Transport Supervisor history row (ADR-0018 T4/T7). Grants no
 * permission.
 *
 * @property int $id
 * @property int $season_id
 * @property int $vehicle_run_id
 * @property int $supervisor_employee_id
 * @property Carbon $assigned_at
 * @property Carbon|null $ended_at
 * @property string|null $reason
 */
class TransportRunSupervisorAssignment extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'season_id',
        'vehicle_run_id',
        'supervisor_employee_id',
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
        return $this->belongsTo(Employee::class, 'supervisor_employee_id');
    }
}
