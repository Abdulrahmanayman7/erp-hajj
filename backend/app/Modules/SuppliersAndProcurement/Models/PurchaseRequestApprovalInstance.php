<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestApprovalInstanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Routed approval path for one PR version. Phase IV WP02.
 */
class PurchaseRequestApprovalInstance extends Model implements TenantOwned
{
    use UsesTenantScope;

    /**
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_no' => 'integer',
            'matrix_version_no' => 'integer',
            'status' => PurchaseRequestApprovalInstanceStatus::class,
            'routed_at' => 'datetime',
        ];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function matrix(): BelongsTo
    {
        return $this->belongsTo(PurchaseApprovalMatrix::class, 'matrix_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(PurchaseApprovalMatrixRule::class, 'rule_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(PurchaseRequestApprovalStep::class, 'instance_id')->orderBy('level_order');
    }
}
