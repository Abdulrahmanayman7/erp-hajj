<?php

namespace App\Modules\Gps\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Tenant-owned GPS provider configuration for the Integration Hub.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string $code
 * @property string $connection_method
 * @property bool $is_enabled
 * @property array<string, mixed>|null $settings_json
 * @property array<string, mixed>|null $credentials_ciphertext
 * @property Carbon|null $last_sync_at
 * @property string|null $last_health_status
 * @property string|null $last_error_sanitized
 * @property int|null $poll_interval_seconds
 */
class GpsProvider extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $table = 'gps_providers';

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'settings_json' => 'array',
            'credentials_ciphertext' => 'encrypted:array',
            'last_sync_at' => 'datetime',
            'poll_interval_seconds' => 'integer',
        ];
    }

    public function deviceBindings(): HasMany
    {
        return $this->hasMany(GpsDeviceBinding::class, 'gps_provider_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return is_array($this->settings_json) ? $this->settings_json : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function credentialBag(): array
    {
        return is_array($this->credentials_ciphertext) ? $this->credentials_ciphertext : [];
    }

    public function hasCredentialsStored(): bool
    {
        return $this->credentialBag() !== [];
    }
}
