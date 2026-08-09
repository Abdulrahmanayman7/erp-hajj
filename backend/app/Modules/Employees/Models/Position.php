<?php

namespace App\Modules\Employees\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use Database\Factories\PositionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant-owned job title catalog entry.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $code
 * @property bool $is_active
 *
 * @see docs/10-decisions/ADR-0005-EMPLOYEE-POSITIONS-CATALOG.md
 */
class Position extends Model implements TenantOwned
{
    /** @use HasFactory<PositionFactory> */
    use HasFactory;

    use UsesTenantScope;

    protected $fillable = [
        'name',
        'code',
        'is_active',
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

    protected static function newFactory(): PositionFactory
    {
        return PositionFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Position $position): void {
            if ($position->isDirty('code')) {
                throw new \LogicException('Position code is immutable after creation.');
            }
            if ($position->isDirty('tenant_id')) {
                throw new \LogicException('Position tenant_id is immutable.');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }
}
