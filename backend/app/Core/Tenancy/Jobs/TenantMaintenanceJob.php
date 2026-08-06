<?php

namespace App\Core\Tenancy\Jobs;

/**
 * Marker for maintenance/cleanup jobs (docs/02-architecture/MULTI_TENANCY.md §9):
 * unlike business jobs, these MAY run while the tenant is suspended or
 * pending (e.g. temp-file cleanup). Business jobs are released for retry
 * instead. Archived tenants cancel both classifications.
 */
interface TenantMaintenanceJob {}
