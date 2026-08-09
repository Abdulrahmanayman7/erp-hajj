<?php

namespace App\Modules\Contracts\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use Database\Factories\ContractCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant-owned contract category catalog entry.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $code
 * @property bool $is_active
 */
class ContractCategory extends Model implements TenantOwned
{
    /** @use HasFactory<ContractCategoryFactory> */
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

    protected static function newFactory(): ContractCategoryFactory
    {
        return ContractCategoryFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (ContractCategory $category): void {
            if ($category->isDirty('tenant_id')) {
                throw new \LogicException('Contract category tenant_id is immutable.');
            }
            if ($category->isDirty('code') && $category->getOriginal('code') !== null) {
                throw new \LogicException('Contract category code is immutable after creation.');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'contract_category_id');
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }
}
