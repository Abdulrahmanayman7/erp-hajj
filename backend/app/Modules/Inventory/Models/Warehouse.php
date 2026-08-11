<?php

namespace App\Modules\Inventory\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\Documents\Models\Document;
use App\Modules\Employees\Models\Employee;
use App\Modules\Inventory\Support\WarehouseNumberGenerator;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Warehouse extends Model implements TenantOwned
{
    /** @use HasFactory<WarehouseFactory> */
    use HasFactory;

    use UsesTenantScope;

    protected $fillable = [
        'name',
        'description',
        'location',
        'organization_unit_id',
        'responsible_employee_id',
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
        ];
    }

    protected static function newFactory(): WarehouseFactory
    {
        return WarehouseFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (Warehouse $warehouse): void {
            $number = $warehouse->getAttribute('warehouse_number');
            if ($number === null || $number === '') {
                $warehouse->setAttribute(
                    'warehouse_number',
                    app(WarehouseNumberGenerator::class)->next(),
                );
            }
        });

        static::updating(function (Warehouse $warehouse): void {
            if ($warehouse->isDirty('warehouse_number')) {
                throw new \LogicException('Warehouse number is immutable after creation.');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_id');
    }

    public function responsibleEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'responsible_employee_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class, 'warehouse_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'warehouse_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'linkable');
    }
}
