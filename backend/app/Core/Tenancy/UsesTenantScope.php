<?php

namespace App\Core\Tenancy;

use App\Core\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mechanics for TenantOwned models (docs/02-architecture/MULTI_TENANCY.md §6):
 *
 * - registers TenantScope (fail-closed automatic filtering);
 * - force-sets tenant_id from TenantContext on create — any client-supplied
 *   value is OVERWRITTEN (tenant_id must never be $fillable; this is the
 *   second, independent defense layer);
 * - prevents tenant_id mutation after creation (records never move between
 *   tenants — a move would detach audit history and relationships);
 * - keeps soft-deleted rows tenant-scoped (the global scope applies to
 *   withTrashed() queries too).
 *
 * The trait never resolves tenants from the request, never reads tenant_id
 * from input, never queries across tenants, and contains no bypass logic.
 */
trait UsesTenantScope
{
    public static function bootUsesTenantScope(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            // Force-set from context; overwrite anything already present.
            $model->setAttribute('tenant_id', app(TenantContext::class)->require()->id);
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('tenant_id')) {
                throw new \LogicException(sprintf(
                    'tenant_id is immutable: attempted to move [%s#%s] between tenants.',
                    $model::class,
                    $model->getKey(),
                ));
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
