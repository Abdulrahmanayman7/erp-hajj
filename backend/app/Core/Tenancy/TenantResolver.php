<?php

namespace App\Core\Tenancy;

use App\Core\Tenancy\Models\Tenant;
use Illuminate\Http\Request;

/**
 * Tenant resolution abstraction. The MVP implementation resolves from the
 * authenticated user only; future transports (subdomain, custom domain,
 * API token, SSO) are new implementations behind this interface + an ADR —
 * business modules never change (docs/02-architecture/MULTI_TENANCY.md §4).
 */
interface TenantResolver
{
    public function resolve(Request $request): ?Tenant;
}
