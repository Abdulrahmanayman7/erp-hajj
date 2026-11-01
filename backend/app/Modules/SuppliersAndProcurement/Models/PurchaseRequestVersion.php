<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestKind;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestPriority;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequestVersion extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'status' => PurchaseRequestStatus::class,
            'request_kind' => PurchaseRequestKind::class,
            'priority' => PurchaseRequestPriority::class,
            'version_no' => 'integer',
            'estimated_total_priced' => 'decimal:2',
            'has_unpriced_lines' => 'boolean',
            'duplicate_warning_acknowledged_at' => 'datetime',
            'snapshot_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \LogicException('Purchase request versions are immutable.');
        });

        static::deleting(function (): void {
            throw new \LogicException('Purchase request versions cannot be deleted.');
        });
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseRequestVersionLine::class)->orderBy('line_no');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
