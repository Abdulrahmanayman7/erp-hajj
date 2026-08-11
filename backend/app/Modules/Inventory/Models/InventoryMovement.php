<?php

namespace App\Modules\Inventory\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\Inventory\Enums\MovementDirection;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Exceptions\InventoryDomainException;
use App\Modules\Inventory\Support\InventoryMovementNumberGenerator;
use Database\Factories\InventoryMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model implements TenantOwned
{
    /** @use HasFactory<InventoryMovementFactory> */
    use HasFactory;

    use UsesTenantScope;

    protected $fillable = [
        'type',
        'warehouse_id',
        'inventory_item_id',
        'quantity',
        'direction',
        'balance_before',
        'balance_after',
        'transfer_group_id',
        'reason',
        'reference',
        'performed_by',
        'occurred_at',
        'correlation_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'direction' => MovementDirection::class,
            'quantity' => 'decimal:3',
            'balance_before' => 'decimal:3',
            'balance_after' => 'decimal:3',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function newFactory(): InventoryMovementFactory
    {
        return InventoryMovementFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (InventoryMovement $movement): void {
            $number = $movement->getAttribute('movement_number');
            if ($number === null || $number === '') {
                $movement->setAttribute(
                    'movement_number',
                    app(InventoryMovementNumberGenerator::class)->next(),
                );
            }
        });

        static::updating(function (): void {
            throw InventoryDomainException::immutable();
        });

        static::deleting(function (): void {
            throw InventoryDomainException::immutable();
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

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function performer(): BelongsTo
    {
        return $this->performedBy();
    }
}
