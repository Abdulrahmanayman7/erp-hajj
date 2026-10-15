<?php

namespace App\Modules\Transportation\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\Transportation\Enums\TransportPointType;
use Illuminate\Database\Eloquent\Model;

/**
 * Tenant-level structured origin/destination (ADR-0018 T2).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property TransportPointType $point_type
 * @property bool $is_active
 * @property string|null $notes
 */
class TransportPoint extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'code',
        'name',
        'point_type',
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
            'point_type' => TransportPointType::class,
            'is_active' => 'boolean',
        ];
    }

    public function isUsed(): bool
    {
        return TransportMovement::query()
            ->where(fn ($query) => $query
                ->where('origin_point_id', $this->id)
                ->orWhere('destination_point_id', $this->id))
            ->exists();
    }
}
