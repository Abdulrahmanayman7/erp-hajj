<?php

namespace App\Core\Tenancy\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use Database\Factories\TenantSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * First tenant-owned model — exercises the full scoping stack.
 * No soft deletes: settings are overwritten; history lives in audit records.
 */
class TenantSetting extends Model implements TenantOwned
{
    /** @use HasFactory<TenantSettingFactory> */
    use HasFactory;

    use UsesTenantScope;

    // tenant_id is deliberately NOT fillable (defense layer 1);
    // the trait force-sets it from TenantContext (defense layer 2).
    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    protected static function newFactory(): TenantSettingFactory
    {
        return TenantSettingFactory::new();
    }
}
