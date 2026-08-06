<?php

namespace App\Core\Tenancy;

use App\Core\Tenancy\Exceptions\MissingTenantContextException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Automatic tenant filtering for every query on TenantOwned models.
 *
 * Fail closed: with no TenantContext (and no runAsTenant wrapper) the scope
 * THROWS instead of returning all rows (catastrophic leak) or zero rows
 * (hides the bug). PlatformContext grants no data access — a tenant-owned
 * query inside it without runAsTenant still throws.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if (! $context->has()) {
            throw new MissingTenantContextException(
                sprintf('Attempted to query tenant-owned model [%s] without a tenant context.', $model::class),
            );
        }

        // Qualified with the table name to stay correct in joins.
        $builder->where($model->qualifyColumn('tenant_id'), $context->id());
    }
}
