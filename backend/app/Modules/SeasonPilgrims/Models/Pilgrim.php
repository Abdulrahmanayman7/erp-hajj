<?php

namespace App\Modules\SeasonPilgrims\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\SeasonPilgrims\Enums\PilgrimGender;
use Database\Factories\PilgrimFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stable tenant-level identity (F3, F4). Holds no seasonal,
 * accommodation or transportation state.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $full_name
 * @property PilgrimGender|null $gender
 * @property string|null $nationality
 */
class Pilgrim extends Model implements TenantOwned
{
    /** @use HasFactory<PilgrimFactory> */
    use HasFactory;

    use UsesTenantScope;

    protected $fillable = [
        'full_name',
        'gender',
        'nationality',
        'phone',
        'notes',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => PilgrimGender::class,
        ];
    }

    protected static function newFactory(): PilgrimFactory
    {
        return PilgrimFactory::new();
    }

    public function identityDocuments(): HasMany
    {
        return $this->hasMany(PilgrimIdentityDocument::class);
    }

    public function seasonPilgrims(): HasMany
    {
        return $this->hasMany(SeasonPilgrim::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
