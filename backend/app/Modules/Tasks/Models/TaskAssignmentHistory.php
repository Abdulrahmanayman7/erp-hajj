<?php

namespace App\Modules\Tasks\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\Employees\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only task assignment history row.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $task_id
 * @property int|null $from_employee_id
 * @property int|null $to_employee_id
 * @property int $performed_by
 * @property string|null $comment
 * @property string $correlation_id
 * @property Carbon $created_at
 */
class TaskAssignmentHistory extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $table = 'task_assignment_history';

    public $timestamps = false;

    protected $fillable = [
        'task_id',
        'from_employee_id',
        'to_employee_id',
        'performed_by',
        'comment',
        'correlation_id',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function fromEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'from_employee_id');
    }

    public function toEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'to_employee_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
