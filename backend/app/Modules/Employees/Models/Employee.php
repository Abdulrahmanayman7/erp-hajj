<?php

namespace App\Modules\Employees\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\Employees\Enums\EmployeeStatus;
use App\Modules\Employees\Support\EmployeeNumberGenerator;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Tenant-owned personnel record (distinct from User account identity).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $user_id
 * @property string $employee_number
 * @property string $full_name
 * @property string|null $phone
 * @property string|null $email
 * @property int $organization_unit_id
 * @property int|null $position_id
 * @property int|null $supervisor_id
 * @property EmployeeStatus $status
 * @property Carbon|null $hire_date
 * @property string|null $notes
 */
class Employee extends Model implements TenantOwned
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    use UsesTenantScope;

    protected $fillable = [
        'user_id',
        'full_name',
        'phone',
        'email',
        'organization_unit_id',
        'position_id',
        'supervisor_id',
        'status',
        'hire_date',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EmployeeStatus::class,
            'hire_date' => 'date',
        ];
    }

    protected static function newFactory(): EmployeeFactory
    {
        return EmployeeFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (Employee $employee): void {
            $number = $employee->getAttribute('employee_number');
            if ($number === null || $number === '') {
                $employee->setAttribute(
                    'employee_number',
                    app(EmployeeNumberGenerator::class)->next(),
                );
            }
        });

        static::updating(function (Employee $employee): void {
            if ($employee->isDirty('employee_number')) {
                throw new \LogicException('Employee number is immutable after creation.');
            }
            if ($employee->isDirty('tenant_id')) {
                throw new \LogicException('Employee tenant_id is immutable.');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supervisor_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(self::class, 'supervisor_id');
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }
}
