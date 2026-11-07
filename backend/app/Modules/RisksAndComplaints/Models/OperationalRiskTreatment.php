<?php

namespace App\Modules\RisksAndComplaints\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\Employees\Models\Employee;
use App\Modules\RisksAndComplaints\Enums\TreatmentStatus;
use Database\Factories\OperationalRiskTreatmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $risk_id
 * @property string $title
 * @property string $plan_body
 * @property int $owner_employee_id
 * @property Carbon|null $due_at
 * @property TreatmentStatus $status
 * @property int $created_by
 */
class OperationalRiskTreatment extends Model implements TenantOwned
{
    /** @use HasFactory<OperationalRiskTreatmentFactory> */
    use HasFactory;

    use UsesTenantScope;

    protected $fillable = [
        'risk_id',
        'title',
        'plan_body',
        'owner_employee_id',
        'due_at',
        'status',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'status' => TreatmentStatus::class,
        ];
    }

    protected static function newFactory(): OperationalRiskTreatmentFactory
    {
        return OperationalRiskTreatmentFactory::new();
    }

    public function risk(): BelongsTo
    {
        return $this->belongsTo(OperationalRisk::class, 'risk_id');
    }

    public function ownerEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'owner_employee_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
