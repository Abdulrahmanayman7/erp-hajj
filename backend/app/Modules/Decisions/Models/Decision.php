<?php

namespace App\Modules\Decisions\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\Decisions\Enums\DecisionStatus;
use App\Modules\Decisions\Support\DecisionNumberGenerator;
use App\Modules\Employees\Models\Employee;
use App\Modules\Meetings\Models\MeetingRecommendation;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\Tasks\Models\Task;
use Database\Factories\DecisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Tenant-owned formal governance decision.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $decision_number
 * @property string $title
 * @property string $body
 * @property string|null $notes
 * @property DecisionStatus $status
 * @property int|null $source_recommendation_id
 * @property int|null $organization_unit_id
 * @property int|null $issued_by_employee_id
 * @property int|null $responsible_employee_id
 * @property Carbon|null $effective_date
 * @property Carbon|null $due_date
 * @property int $created_by
 */
class Decision extends Model implements TenantOwned
{
    /** @use HasFactory<DecisionFactory> */
    use HasFactory;

    use UsesTenantScope;

    protected $fillable = [
        'title',
        'body',
        'notes',
        'status',
        'source_recommendation_id',
        'organization_unit_id',
        'issued_by_employee_id',
        'responsible_employee_id',
        'effective_date',
        'due_date',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DecisionStatus::class,
            'effective_date' => 'date',
            'due_date' => 'date',
        ];
    }

    protected static function newFactory(): DecisionFactory
    {
        return DecisionFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (Decision $decision): void {
            $number = $decision->getAttribute('decision_number');
            if ($number === null || $number === '') {
                $decision->setAttribute(
                    'decision_number',
                    app(DecisionNumberGenerator::class)->next(),
                );
            }
        });

        static::updating(function (Decision $decision): void {
            if ($decision->isDirty('decision_number')) {
                throw new \LogicException('Decision number is immutable after creation.');
            }
            if ($decision->isDirty('tenant_id')) {
                throw new \LogicException('Decision tenant_id is immutable.');
            }
            if ($decision->isDirty('source_recommendation_id')) {
                throw new \LogicException('Decision source_recommendation_id is immutable after creation.');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sourceRecommendation(): BelongsTo
    {
        return $this->belongsTo(MeetingRecommendation::class, 'source_recommendation_id');
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_id');
    }

    public function issuedByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'issued_by_employee_id');
    }

    public function responsibleEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'responsible_employee_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusTransitions(): HasMany
    {
        return $this->hasMany(DecisionStatusTransition::class)->orderBy('created_at')->orderBy('id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
