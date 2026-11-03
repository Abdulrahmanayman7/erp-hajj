<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SuppliersAndProcurement\Enums\ProcurementRfqStatus;
use Database\Factories\ProcurementRfqFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementRfq extends Model implements TenantOwned
{
    /** @use HasFactory<ProcurementRfqFactory> */
    use HasFactory;

    use UsesTenantScope;

    protected $table = 'procurement_rfqs';

    protected $fillable = [
        'title',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProcurementRfqStatus::class,
            'published_at' => 'datetime',
            'awarded_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ProcurementRfqFactory
    {
        return ProcurementRfqFactory::new();
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function selectedSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'selected_supplier_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProcurementRfqLine::class, 'procurement_rfq_id')->orderBy('line_no');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(ProcurementRfqInvitation::class, 'procurement_rfq_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(SupplierOffer::class, 'procurement_rfq_id');
    }

    public function awarder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }
}
