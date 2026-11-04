<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptLine extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $table = 'goods_receipt_lines';

    protected $fillable = [
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'quantity' => 'decimal:4',
        ];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function inventoryMovement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class);
    }
}
