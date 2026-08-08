<?php

namespace App\Modules\Authorization\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Tenant-owned role.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property bool $is_system
 * @property bool $is_active
 * @property int|null $created_by
 */
class Role extends Model implements TenantOwned
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    use UsesTenantScope;

    public const CODE_TENANT_OWNER = 'tenant_owner';

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_system',
        'is_active',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): RoleFactory
    {
        return RoleFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Role $role): void {
            if ($role->isDirty('code')) {
                throw new \LogicException('Role code is immutable after creation.');
            }
            if ($role->isDirty('is_system')) {
                throw new \LogicException('Role is_system flag cannot be mutated after creation.');
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')
            ->withPivot(['tenant_id', 'assigned_by', 'created_at']);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->withPivot(['tenant_id', 'assigned_by', 'created_at']);
    }

    public function isTenantOwner(): bool
    {
        return $this->code === self::CODE_TENANT_OWNER;
    }
}
