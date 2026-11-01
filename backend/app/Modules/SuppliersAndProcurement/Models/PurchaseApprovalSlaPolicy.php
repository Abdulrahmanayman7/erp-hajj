<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Tenant SLA target hours by PR priority × approval level_order. Phase IV WP05 / IV-D12.
 */
class PurchaseApprovalSlaPolicy extends Model implements TenantOwned
{
    use UsesTenantScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'priority',
        'level_order',
        'target_hours',
        'reminder_offset_hours',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level_order' => 'integer',
            'target_hours' => 'integer',
            'reminder_offset_hours' => 'integer',
        ];
    }
}
