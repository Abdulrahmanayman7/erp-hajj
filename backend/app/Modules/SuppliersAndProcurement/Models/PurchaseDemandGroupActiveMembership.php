<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Exclusive active membership row — composite PK (tenant_id, purchase_request_id).
 */
class PurchaseDemandGroupActiveMembership extends Model implements TenantOwned
{
    use UsesTenantScope;

    public $incrementing = false;

    protected $primaryKey = 'purchase_request_id';

    public $timestamps = false;

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
            'created_at' => 'datetime',
        ];
    }

    public function demandGroup(): BelongsTo
    {
        return $this->belongsTo(PurchaseDemandGroup::class, 'demand_group_id');
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PurchaseDemandGroupItem::class, 'item_id');
    }
}
