<?php

namespace App\Core\Authorization\Models;

use App\Models\User;
use App\Modules\Authorization\Models\Permission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Platform-only role (no tenant_id). Grantable only to users with tenant_id NULL.
 */
class PlatformRole extends Model
{
    public const CODE_SUPER_ADMIN = 'platform_super_admin';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_system',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'platform_role_permissions')
            ->withPivot(['created_at']);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'platform_user_roles')
            ->withPivot(['assigned_by', 'created_at']);
    }
}
