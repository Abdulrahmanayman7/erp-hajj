<?php

namespace App\Modules\Authorization\Models;

use Database\Factories\PermissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Platform-global permission catalog row (no tenant_id).
 *
 * @property int $id
 * @property string $name
 * @property string $display_name
 * @property string $module
 * @property string|null $description
 */
class Permission extends Model
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'module',
        'description',
    ];

    protected static function newFactory(): PermissionFactory
    {
        return PermissionFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Permission $permission): void {
            if ($permission->isDirty('name')) {
                throw new \LogicException('Permission name is immutable.');
            }
        });
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions')
            ->withPivot(['tenant_id', 'assigned_by', 'created_at']);
    }
}
