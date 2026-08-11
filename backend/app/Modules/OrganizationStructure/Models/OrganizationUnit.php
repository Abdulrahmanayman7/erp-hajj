<?php

namespace App\Modules\OrganizationStructure\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\OrganizationStructure\Enums\OrganizationUnitStatus;
use App\Modules\OrganizationStructure\Enums\OrganizationUnitType;
use Database\Factories\OrganizationUnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant-owned organizational unit (department / section / unit).
 *
 * manager_user_id = user responsible for managing this unit (NOT employee line supervisor).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $parent_id
 * @property string $name
 * @property string $code
 * @property OrganizationUnitType $type
 * @property OrganizationUnitStatus $status
 * @property int|null $manager_user_id
 * @property int $sort_order
 */
class OrganizationUnit extends Model implements TenantOwned
{
    /** @use HasFactory<OrganizationUnitFactory> */
    use HasFactory;

    use UsesTenantScope;

    protected $fillable = [
        'parent_id',
        'name',
        'code',
        'type',
        'status',
        'manager_user_id',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OrganizationUnitType::class,
            'status' => OrganizationUnitStatus::class,
            'sort_order' => 'integer',
        ];
    }

    protected static function newFactory(): OrganizationUnitFactory
    {
        return OrganizationUnitFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (OrganizationUnit $unit): void {
            if ($unit->isDirty('code')) {
                throw new \LogicException('Organization unit code is immutable after creation.');
            }
            if ($unit->isDirty('tenant_id')) {
                throw new \LogicException('Organization unit tenant_id is immutable.');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Unit responsible manager (organizational accountability — not employee supervisor).
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }
}
