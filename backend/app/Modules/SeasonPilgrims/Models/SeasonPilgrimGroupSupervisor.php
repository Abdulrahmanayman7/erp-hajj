<?php

namespace App\Modules\SeasonPilgrims\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\Employees\Models\Employee;
use App\Modules\SeasonPilgrims\Enums\SupervisorRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Supervisor team assignment with history (F27). Operational only — grants
 * no application permission.
 *
 * @property int $id
 * @property int $season_pilgrim_group_id
 * @property int $employee_id
 * @property SupervisorRole $role
 * @property Carbon|null $ended_at
 */
class SeasonPilgrimGroupSupervisor extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'season_pilgrim_group_id',
        'employee_id',
        'role',
        'assigned_at',
        'assigned_by',
        'ended_at',
        'ended_by',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['active_flag', 'primary_active_flag'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => SupervisorRole::class,
            'assigned_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(SeasonPilgrimGroup::class, 'season_pilgrim_group_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
