<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\SupplierStatus;
use App\Modules\SuppliersAndProcurement\Enums\SupplierType;
use App\Modules\SuppliersAndProcurement\Support\SupplierNumberGenerator;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model implements TenantOwned
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    use UsesTenantScope;

    /**
     * Privileged lifecycle / identity fields are intentionally excluded from
     * mass assignment and set only by domain Actions / model hooks.
     *
     * @var list<string>
     */
    protected $fillable = [
        'supplier_type',
        'display_name',
        'legal_name',
        'country_code',
        'legal_id_type',
        'legal_id_value',
        'notes',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SupplierStatus::class,
            'supplier_type' => SupplierType::class,
            'activated_at' => 'datetime',
        ];
    }

    protected static function newFactory(): SupplierFactory
    {
        return SupplierFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (Supplier $supplier): void {
            if ($supplier->getAttribute('status') === null) {
                $supplier->setAttribute('status', SupplierStatus::Draft);
            }

            $number = $supplier->getAttribute('supplier_number');
            if ($number === null || $number === '') {
                $supplier->setAttribute('supplier_number', app(SupplierNumberGenerator::class)->next());
            }
        });

        static::updating(function (Supplier $supplier): void {
            if ($supplier->isDirty('supplier_number')) {
                throw new \LogicException('Supplier number is immutable after creation.');
            }
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(SupplierCategory::class, 'supplier_category_supplier')
            ->withTimestamps()
            ->withPivot(['tenant_id']);
    }

    public function coverages(): HasMany
    {
        return $this->hasMany(SupplierCategoryCoverage::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(SupplierBankAccount::class);
    }

    public function documentRecords(): HasMany
    {
        return $this->hasMany(SupplierDocumentRecord::class);
    }

    public function statusTransitions(): HasMany
    {
        return $this->hasMany(SupplierStatusTransition::class)->orderBy('created_at');
    }
}
