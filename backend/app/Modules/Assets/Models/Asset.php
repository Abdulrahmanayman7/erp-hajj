<?php

namespace App\Modules\Assets\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\Assets\Enums\AssetCondition;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Exceptions\AssetDomainException;
use App\Modules\Assets\Support\AssetNumberGenerator;
use App\Modules\Documents\Models\Document;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Asset extends Model implements TenantOwned
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory;

    use UsesTenantScope;

    protected $fillable = [
        'name',
        'description',
        'category_id',
        'serial_number',
        'barcode',
        'condition',
        'warehouse_id',
        'organization_unit_id',
        'purchase_value',
        'acquisition_date',
        'notes',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AssetStatus::class,
            'condition' => AssetCondition::class,
            'purchase_value' => 'decimal:2',
            'acquisition_date' => 'date',
        ];
    }

    protected static function newFactory(): AssetFactory
    {
        return AssetFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (Asset $asset): void {
            $number = $asset->getAttribute('asset_number');
            if ($number === null || $number === '') {
                $asset->setAttribute(
                    'asset_number',
                    app(AssetNumberGenerator::class)->next(),
                );
            }

            if ($asset->getAttribute('status') === null) {
                $asset->setAttribute('status', AssetStatus::Available);
            }
        });

        static::updating(function (Asset $asset): void {
            if ($asset->isDirty('asset_number')) {
                throw AssetDomainException::immutable();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_id');
    }

    public function currentCustody(): BelongsTo
    {
        return $this->belongsTo(AssetCustody::class, 'current_custody_id');
    }

    public function custodies(): HasMany
    {
        return $this->hasMany(AssetCustody::class, 'asset_id');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(AssetStatusTransition::class, 'asset_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'linkable');
    }
}
