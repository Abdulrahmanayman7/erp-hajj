<?php

namespace App\Modules\SeasonProjects\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\Employees\Models\Employee;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonProjects\Enums\SeasonProjectStatus;
use App\Modules\SeasonProjects\Exceptions\SeasonProjectDomainException;
use App\Modules\SeasonProjects\Support\SeasonProjectNumberGenerator;
use App\Modules\Tasks\Models\Task;
use Database\Factories\SeasonProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Tenant + season scoped operational project (S06).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $season_id
 * @property string $number
 * @property string $title
 * @property string|null $description
 * @property SeasonProjectStatus $status
 * @property int|null $owner_employee_id
 * @property string|null $budget_amount
 * @property string $currency_code
 * @property string $progress_percent
 * @property string|null $delay_summary
 * @property Carbon|null $activated_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $deleted_at
 */
class SeasonProject extends Model implements TenantOwned
{
    /** @use HasFactory<SeasonProjectFactory> */
    use HasFactory;

    use SoftDeletes;
    use UsesTenantScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'season_id',
        'title',
        'description',
        'owner_employee_id',
        'budget_amount',
        'currency_code',
        'progress_percent',
        'delay_summary',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SeasonProjectStatus::class,
            'budget_amount' => 'decimal:2',
            'progress_percent' => 'decimal:2',
            'activated_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function newFactory(): SeasonProjectFactory
    {
        return SeasonProjectFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (SeasonProject $project): void {
            if ($project->getAttribute('status') === null) {
                $project->setAttribute('status', SeasonProjectStatus::Draft);
            }

            $number = $project->getAttribute('number');
            if ($number === null || $number === '') {
                $seasonId = (int) $project->getAttribute('season_id');
                $project->setAttribute(
                    'number',
                    app(SeasonProjectNumberGenerator::class)->next($seasonId),
                );
            }
        });

        static::updating(function (SeasonProject $project): void {
            if ($project->isDirty('number')) {
                throw new \LogicException('Season project number is immutable after creation.');
            }
            if ($project->isDirty('season_id')) {
                throw new \LogicException('Season project season_id is immutable after creation.');
            }
        });

        static::deleting(function (SeasonProject $project): void {
            if ($project->isForceDeleting()) {
                throw SeasonProjectDomainException::forceDeleteForbidden();
            }

            $status = $project->status instanceof SeasonProjectStatus
                ? $project->status
                : SeasonProjectStatus::from((string) $project->status);

            if (! $status->allowsSoftDelete()) {
                throw SeasonProjectDomainException::softDeleteNotAllowed();
            }

            if ($project->tasks()->exists() || $project->budgetPoLinks()->exists()) {
                throw SeasonProjectDomainException::softDeleteHasChildren();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function ownerEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'owner_employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function phases(): HasMany
    {
        return $this->hasMany(SeasonProjectPhase::class, 'project_id')->orderBy('sort_order');
    }

    public function budgetPoLinks(): HasMany
    {
        return $this->hasMany(SeasonProjectBudgetPoLink::class, 'project_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'project_id');
    }
}
