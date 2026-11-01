<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestKind;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestPriority;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use Database\Factories\PurchaseRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequest extends Model implements TenantOwned
{
    /** @use HasFactory<PurchaseRequestFactory> */
    use HasFactory;

    use UsesTenantScope;

    protected $fillable = [
        'title',
        'purpose',
        'urgency_justification',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseRequestStatus::class,
            'request_kind' => PurchaseRequestKind::class,
            'priority' => PurchaseRequestPriority::class,
            'version_no' => 'integer',
            'estimated_total_priced' => 'decimal:2',
            'has_unpriced_lines' => 'boolean',
            'priority_verified_at' => 'datetime',
            'duplicate_warning_acknowledged_at' => 'datetime',
            'procurement_engaged_at' => 'datetime',
            'submitted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'routing_blocked_at' => 'datetime',
        ];
    }

    protected static function newFactory(): PurchaseRequestFactory
    {
        return PurchaseRequestFactory::new();
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseRequestLine::class)->orderBy('line_no');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PurchaseRequestVersion::class)->orderByDesc('version_no');
    }

    public function financialReviews(): HasMany
    {
        return $this->hasMany(PurchaseRequestFinancialReview::class)->orderByDesc('reviewed_at');
    }

    public function approvalInstances(): HasMany
    {
        return $this->hasMany(PurchaseRequestApprovalInstance::class)->orderByDesc('version_no');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function beneficiaryUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'beneficiary_organization_unit_id');
    }

    public function optionalSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'optional_supplier_id');
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function priorityVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'priority_verified_by');
    }
}
