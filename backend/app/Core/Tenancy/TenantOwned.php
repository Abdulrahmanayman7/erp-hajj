<?php

namespace App\Core\Tenancy;

/**
 * Marker contract: the model's table carries tenant_id and every query,
 * create, update, and delete is tenant-scoped. Implementing models must use
 * the UsesTenantScope trait (docs/02-architecture/MULTI_TENANCY.md §6).
 */
interface TenantOwned {}
