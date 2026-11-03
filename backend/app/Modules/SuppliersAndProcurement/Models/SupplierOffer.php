<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\SupplierOfferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierOffer extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $table = 'supplier_offers';

    protected $fillable = [
        'notes',
        'currency',
        'lead_time_days',
    ];

    protected function casts(): array
    {
        return [
            'status' => SupplierOfferStatus::class,
            'lead_time_days' => 'integer',
            'submitted_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(ProcurementRfq::class, 'procurement_rfq_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function withdrawer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'withdrawn_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SupplierOfferLine::class, 'supplier_offer_id')->orderBy('line_no');
    }
}
