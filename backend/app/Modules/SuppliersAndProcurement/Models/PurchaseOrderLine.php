<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\Inventory\Models\InventoryItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $table = 'purchase_order_lines';

    protected $fillable = [
        'description',
        'specifications',
        'quantity_ordered',
        'unit',
        'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'quantity_ordered' => 'decimal:4',
            'quantity_received' => 'decimal:4',
            'unit_price' => 'decimal:4',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function rfqLine(): BelongsTo
    {
        return $this->belongsTo(ProcurementRfqLine::class, 'procurement_rfq_line_id');
    }

    public function offerLine(): BelongsTo
    {
        return $this->belongsTo(SupplierOfferLine::class, 'supplier_offer_line_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
