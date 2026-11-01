<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestApprovalStepStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ordered level within an approval instance (pool eligibility — no assignee). Phase IV WP02.
 */
class PurchaseRequestApprovalStep extends Model implements TenantOwned
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
            'level_order' => 'integer',
            'status' => PurchaseRequestApprovalStepStatus::class,
            'due_at' => 'datetime',
            'escalated_at' => 'datetime',
            'acted_at' => 'datetime',
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestApprovalInstance::class, 'instance_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
