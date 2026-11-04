<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierOfferLine extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $table = 'supplier_offer_lines';

    protected $fillable = [
        'quantity',
        'unit_price',
        'lead_time_days',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'lead_time_days' => 'integer',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(SupplierOffer::class, 'supplier_offer_id');
    }

    public function rfqLine(): BelongsTo
    {
        return $this->belongsTo(ProcurementRfqLine::class, 'procurement_rfq_line_id');
    }
}
