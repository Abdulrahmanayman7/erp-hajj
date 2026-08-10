<?php

namespace App\Models;

use App\Core\Auth\AvatarGroup;
use App\Core\Auth\UserStatus;
use App\Core\Authorization\EffectivePermissions;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Authorization\Models\Role;
use App\Modules\Employees\Models\Employee;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Hybrid model (docs/03-database/DATABASE_PRINCIPLES.md):
 * tenant_id NULL = platform user; NOT NULL = tenant user.
 * tenant_id and status are deliberately NOT mass assignable from public auth.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'avatar_group' => AvatarGroup::class,
        ];
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => mb_strtolower(trim($value)),
        );
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot(['tenant_id', 'assigned_by', 'created_at']);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function isPlatformUser(): bool
    {
        return $this->tenant_id === null;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function hasPermission(string $permission): bool
    {
        return app(EffectivePermissions::class)->hasPermission($this, $permission);
    }

    /**
     * @param  list<string>  $permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        return app(EffectivePermissions::class)->hasAnyPermission($this, $permissions);
    }

    public function hasRole(string $roleCode): bool
    {
        return app(EffectivePermissions::class)->hasRole($this, $roleCode);
    }
}
