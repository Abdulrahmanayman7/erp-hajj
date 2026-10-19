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
 * @property int $facility_id
 * @property int|null $structure_id
 * @property string $unit_kind
 * @property string $code
 */
class AccommodationUnit extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'facility_id',
        'structure_id',
        'unit_kind',
        'code',
        'created_by',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(AccommodationFacility::class, 'facility_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(AccommodationStructure::class, 'structure_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(AccommodationPosition::class, 'unit_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
