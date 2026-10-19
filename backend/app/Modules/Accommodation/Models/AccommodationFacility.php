<?php

namespace App\Modules\Accommodation\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $kind
 * @property string $code
 * @property string $name
 * @property bool $is_active
 */
class AccommodationFacility extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'kind',
        'code',
        'name',
        'is_active',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function structures(): HasMany
    {
        return $this->hasMany(AccommodationStructure::class, 'facility_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(AccommodationUnit::class, 'facility_id');
    }

    public function seasonFacilities(): HasMany
    {
        return $this->hasMany(AccommodationSeasonFacility::class, 'facility_id');
    }
}
