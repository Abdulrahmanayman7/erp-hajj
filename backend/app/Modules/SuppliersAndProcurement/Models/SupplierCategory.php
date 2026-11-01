<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use Database\Factories\SupplierCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SupplierCategory extends Model implements TenantOwned
{
    /** @use HasFactory<SupplierCategoryFactory> */
    use HasFactory;

    use UsesTenantScope;

    protected $fillable = [
        'name',
        'code',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): SupplierCategoryFactory
    {
        return SupplierCategoryFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (SupplierCategory $category): void {
            if ($category->isDirty('code') && $category->getOriginal('code') !== null) {
                throw new \LogicException('Supplier category code is immutable after creation.');
            }
        });
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'supplier_category_supplier')
            ->withTimestamps()
            ->withPivot(['tenant_id']);
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }
}
