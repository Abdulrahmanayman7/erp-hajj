<?php

namespace App\Modules\Transportation\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\SeasonPilgrims\Contracts\SeasonOwned;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonProgram;
use App\Modules\Transportation\Enums\ServiceLevel;
use App\Modules\Transportation\Enums\VehicleClass;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Transportation entitlement of one SeasonProgram (ADR-0018 T9). A null
 * field means "any".
 *
 * @property int $id
 * @property int $season_id
 * @property int $season_program_id
 * @property VehicleClass|null $vehicle_class
 * @property ServiceLevel|null $service_level
 * @property string|null $notes
 */
class TransportEntitlement extends Model implements SeasonOwned, TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'season_id',
        'season_program_id',
        'vehicle_class',
        'service_level',
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
            'service_level' => ServiceLevel::class,
        ];
    }

    public function owningSeasonId(): int
    {
        return (int) $this->season_id;
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(SeasonProgram::class, 'season_program_id');
    }

    public function matches(?VehicleClass $vehicleClass, ?ServiceLevel $serviceLevel): bool
    {
        return ($this->vehicle_class === null || $this->vehicle_class === $vehicleClass)
            && ($this->service_level === null || $this->service_level === $serviceLevel);
    }
}
