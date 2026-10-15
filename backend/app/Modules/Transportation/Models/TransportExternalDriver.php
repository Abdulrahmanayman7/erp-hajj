<?php

namespace App\Modules\Transportation\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Minimal hired-driver identity (ADR-0018 T4). Not an Employee or User.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $full_name
 * @property string|null $phone
 * @property bool $is_active
 * @property string|null $notes
 */
class TransportExternalDriver extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'full_name',
        'phone',
        'is_active',
        'notes',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function isUsed(): bool
    {
        return TransportSeasonVehicle::query()->where('default_external_driver_id', $this->id)->exists()
            || TransportRunDriverAssignment::query()->where('external_driver_id', $this->id)->exists();
    }
}
