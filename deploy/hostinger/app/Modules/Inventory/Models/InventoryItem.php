<?php

namespace App\Modules\Inventory\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\Documents\Models\Document;
use App\Modules\Inventory\Support\InventoryItemNumberGenerator;
use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class InventoryItem extends Model implements TenantOwned
{
    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory;

    use UsesTenantScope;

    protected $fillable = [
        'name',
        'description',
        'category_id',
        'unit',
        'barcode',
        'minimum_stock',
        'is_active',
        'notes',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'minimum_stock' => 'decimal:3',
        ];
    }

    protected static function newFactory(): InventoryItemFactory
    {
        return InventoryItemFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (InventoryItem $item): void {
            $number = $item->getAttribute('item_number');
            if ($number === null || $number === '') {
                $item->setAttribute(
                    'item_number',
                    app(InventoryItemNumberGenerator::class)->next(),
                );
            }
        });

        static::updating(function (InventoryItem $item): void {
            if ($item->isDirty('item_number')) {
                throw new \LogicException('Inventory item number is immutable after creation.');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class, 'inventory_item_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'inventory_item_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'linkable');
    }
}
