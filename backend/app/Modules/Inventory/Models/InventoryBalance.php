<?php

namespace App\Modules\Inventory\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\Inventory\Enums\StockState;
use App\Modules\Inventory\Exceptions\InventoryDomainException;
use App\Modules\Inventory\Support\InventoryQuantity;
use Database\Factories\InventoryBalanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryBalance extends Model implements TenantOwned
{
    /** @use HasFactory<InventoryBalanceFactory> */
    use HasFactory;

    use UsesTenantScope;

    protected $fillable = [
        'warehouse_id',
        'inventory_item_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'on_hand' => 'decimal:3',
        ];
    }

    protected static function newFactory(): InventoryBalanceFactory
    {
        return InventoryBalanceFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (InventoryBalance $balance): void {
            // Allow on_hand updates only from domain actions (no client mass-assign of on_hand).
            if ($balance->isDirty(['tenant_id', 'warehouse_id', 'inventory_item_id'])) {
                throw InventoryDomainException::immutable();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function stockState(): StockState
    {
        $minimum = InventoryQuantity::format((string) ($this->item?->minimum_stock ?? '0'));

        return StockState::fromQuantities(InventoryQuantity::format((string) $this->on_hand), $minimum);
    }
}
