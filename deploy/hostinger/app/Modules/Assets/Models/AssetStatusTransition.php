<?php

namespace App\Modules\Assets\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Exceptions\AssetDomainException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetStatusTransition extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'asset_id',
        'from_status',
        'to_status',
        'reason',
        'performed_by',
        'correlation_id',
        'custody_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'to_status' => AssetStatus::class,
        ];
    }

    /**
     * Nullable on create (from_status = null).
     *
     * @return Attribute<AssetStatus|null, AssetStatus|string|null>
     */
    protected function fromStatus(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): ?AssetStatus {
                if ($value === null || $value === '') {
                    return null;
                }

                return $value instanceof AssetStatus ? $value : AssetStatus::from((string) $value);
            },
            set: function (mixed $value): ?string {
                if ($value === null || $value === '') {
                    return null;
                }

                return $value instanceof AssetStatus ? $value->value : (string) $value;
            },
        );
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw AssetDomainException::immutable();
        });

        static::deleting(function (): void {
            throw AssetDomainException::immutable();
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function custody(): BelongsTo
    {
        return $this->belongsTo(AssetCustody::class, 'custody_id');
    }
}
