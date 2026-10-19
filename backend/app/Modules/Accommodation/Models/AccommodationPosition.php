<?php

namespace App\Modules\Accommodation\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $unit_id
 * @property string $code
 */
class AccommodationPosition extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'unit_id',
        'code',
        'created_by',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(AccommodationUnit::class, 'unit_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
