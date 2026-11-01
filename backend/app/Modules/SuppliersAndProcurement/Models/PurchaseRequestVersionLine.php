<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestVersionLine extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'quantity' => 'decimal:4',
            'unit_estimate' => 'decimal:2',
            'line_estimate' => 'decimal:2',
            'need_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \LogicException('Purchase request version lines are immutable.');
        });

        static::deleting(function (): void {
            throw new \LogicException('Purchase request version lines cannot be deleted.');
        });
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestVersion::class, 'purchase_request_version_id');
    }
}
