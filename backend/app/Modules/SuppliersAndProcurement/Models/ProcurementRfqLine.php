<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\Inventory\Models\InventoryItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementRfqLine extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $table = 'procurement_rfq_lines';

    protected $fillable = [
        'description',
        'specifications',
        'quantity',
        'unit',
        'need_date',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'quantity' => 'decimal:4',
            'need_date' => 'date',
        ];
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(ProcurementRfq::class, 'procurement_rfq_id');
    }

    public function purchaseRequestLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestLine::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
