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
 * @property int|null $parent_id
 * @property string $kind
 * @property string $name
 */
class AccommodationStructure extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'facility_id',
        'parent_id',
        'kind',
        'name',
        'created_by',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(AccommodationFacility::class, 'facility_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
