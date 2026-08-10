<?php

namespace App\Modules\Tasks\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\Decisions\Models\Decision;
use App\Modules\Employees\Models\Employee;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\Tasks\Enums\TaskPriority;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Support\TaskNumberGenerator;
use App\Modules\Tasks\Support\TaskReferenceValidator;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Tenant-owned execution task.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $task_number
 * @property string $title
 * @property string|null $description
 * @property string|null $notes
 * @property TaskStatus $status
 * @property TaskPriority $priority
 * @property int|null $decision_id
 * @property int|null $organization_unit_id
 * @property int|null $assigned_to_employee_id
 * @property int $progress_percent
 * @property Carbon|null $start_date
 * @property Carbon|null $due_date
 * @property Carbon|null $completed_at
 * @property string|null $completion_notes
 * @property int $created_by
 */
class Task extends Model implements TenantOwned
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    use UsesTenantScope;

    protected $fillable = [
        'title',
        'description',
        'notes',
        'status',
        'priority',
        'decision_id',
        'organization_unit_id',
        'assigned_to_employee_id',
        'progress_percent',
        'start_date',
        'due_date',
        'completed_at',
        'completion_notes',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'progress_percent' => 'integer',
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    protected static function newFactory(): TaskFactory
    {
        return TaskFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (Task $task): void {
            $number = $task->getAttribute('task_number');
            if ($number === null || $number === '') {
                $task->setAttribute(
                    'task_number',
                    app(TaskNumberGenerator::class)->next(),
                );
            }
        });

        static::updating(function (Task $task): void {
            if ($task->isDirty('task_number')) {
                throw new \LogicException('Task number is immutable after creation.');
            }
            if ($task->isDirty('tenant_id')) {
                throw new \LogicException('Task tenant_id is immutable.');
            }
            if ($task->isDirty('decision_id')) {
                throw new \LogicException('Task decision_id is immutable after creation.');
            }
        });
    }

    public function isOverdue(): bool
    {
        if ($this->due_date === null || $this->status->isTerminal()) {
            return false;
        }

        $today = app(TaskReferenceValidator::class)->todayInTenantTimezone();

        return $this->due_date->copy()->startOfDay()->lt($today);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(Decision::class);
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to_employee_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusTransitions(): HasMany
    {
        return $this->hasMany(TaskStatusTransition::class)->orderBy('created_at')->orderBy('id');
    }

    public function assignmentHistory(): HasMany
    {
        return $this->hasMany(TaskAssignmentHistory::class)->orderBy('created_at')->orderBy('id');
    }
}
