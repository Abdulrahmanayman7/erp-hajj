<?php

namespace App\Modules\Contracts\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\Contracts\Enums\ContractStatus;
use App\Modules\Contracts\Enums\CounterpartyKind;
use App\Modules\Contracts\Support\ContractNumberGenerator;
use App\Modules\Employees\Models\Employee;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Tenant-owned contract record.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $contract_number
 * @property string $title
 * @property int $contract_category_id
 * @property ContractStatus $status
 * @property string $counterparty_name
 * @property CounterpartyKind $counterparty_kind
 * @property int|null $employee_id
 * @property int|null $organization_unit_id
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property string|null $value
 * @property string $currency
 * @property string|null $notes
 * @property int $created_by
 * @property int|null $renewed_from_contract_id
 */
class Contract extends Model implements TenantOwned
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory;

    use UsesTenantScope;

    protected $fillable = [
        'title',
        'contract_category_id',
        'status',
        'counterparty_name',
        'counterparty_kind',
        'employee_id',
        'organization_unit_id',
        'start_date',
        'end_date',
        'value',
        'currency',
        'notes',
        'created_by',
        'renewed_from_contract_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContractStatus::class,
            'counterparty_kind' => CounterpartyKind::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'value' => 'decimal:2',
        ];
    }

    protected static function newFactory(): ContractFactory
    {
        return ContractFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (Contract $contract): void {
            $number = $contract->getAttribute('contract_number');
            if ($number === null || $number === '') {
                $contract->setAttribute(
                    'contract_number',
                    app(ContractNumberGenerator::class)->next(),
                );
            }
        });

        static::updating(function (Contract $contract): void {
            if ($contract->isDirty('contract_number')) {
                throw new \LogicException('Contract number is immutable after creation.');
            }
            if ($contract->isDirty('tenant_id')) {
                throw new \LogicException('Contract tenant_id is immutable.');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ContractCategory::class, 'contract_category_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_contract_id');
    }

    public function renewalChild(): HasOne
    {
        return $this->hasOne(self::class, 'renewed_from_contract_id');
    }

    public function statusTransitions(): HasMany
    {
        return $this->hasMany(ContractStatusTransition::class)->orderBy('created_at')->orderBy('id');
    }

    public function isExpiringSoon(?Carbon $today = null): bool
    {
        if ($this->status !== ContractStatus::Executing || $this->end_date === null) {
            return false;
        }

        $today ??= now()->startOfDay();
        $days = max(0, (int) config('contracts.expiring_soon_days', 30));
        $end = $this->end_date->copy()->startOfDay();

        return $end->greaterThanOrEqualTo($today)
            && $end->lessThanOrEqualTo($today->copy()->addDays($days));
    }
}
