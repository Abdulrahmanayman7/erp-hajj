<?php

namespace App\Modules\Transportation\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\Assets\Models\Asset;
use App\Modules\Contracts\Models\Contract;
use App\Modules\Transportation\Enums\VehicleClass;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant-level vehicle registry entry (ADR-0018 T3). Asset / Contract are
 * read-only references.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $vehicle_number
 * @property string $plate_number
 * @property VehicleClass $vehicle_class
 * @property int|null $asset_id
 * @property int|null $contract_id
 * @property bool $is_active
 * @property string|null $notes
 */
class TransportVehicle extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'vehicle_number',
        'plate_number',
        'vehicle_class',
        'asset_id',
        'contract_id',
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
            'vehicle_class' => VehicleClass::class,
            'is_active' => 'boolean',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function seasonVehicles(): HasMany
    {
        return $this->hasMany(TransportSeasonVehicle::class);
    }
}
