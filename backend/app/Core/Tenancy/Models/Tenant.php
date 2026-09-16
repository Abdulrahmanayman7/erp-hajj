<?php

namespace App\Core\Tenancy\Models;

use App\Core\Tenancy\TenantStatus;
use App\Models\User;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Platform model — the tenant registry. Never soft-deleted and never
 * hard-deleted through normal flows; "archived" is the terminal state.
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_code',
        'name',
        'status',
        'locale',
        'timezone',
        'contact_name',
        'contact_email',
        'contact_phone',
        'mail_from_address',
        'mail_from_name',
        'mail_mailer',
        'mail_host',
        'mail_port',
        'mail_encryption',
        'mail_username',
        'mail_password',
        'notes',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'mail_password',
    ];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'suspended_at' => 'datetime',
            'archived_at' => 'datetime',
            'mail_port' => 'integer',
            'mail_password' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        // tenant_code is immutable after creation (docs/09-modules/00-tenancy/DATA_MODEL.md).
        static::updating(function (self $tenant): void {
            if ($tenant->isDirty('tenant_code')) {
                throw new \LogicException('tenant_code is immutable and cannot be changed after creation.');
            }
        });
    }

    /**
     * tenant_code is always stored lowercase.
     */
    protected function tenantCode(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => mb_strtolower($value),
        );
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(TenantSetting::class);
    }

    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }
}
