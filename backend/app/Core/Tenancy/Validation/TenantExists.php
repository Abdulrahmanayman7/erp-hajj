<?php

namespace App\Core\Tenancy\Validation;

use App\Core\Tenancy\TenantContext;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Tenant-scoped "exists" rule (docs/02-architecture/MULTI_TENANCY.md §8).
 * Always adds tenant_id from TenantContext — never from the payload. A
 * record belonging to another tenant behaves exactly like a nonexistent
 * record, blocking cross-tenant reference smuggling.
 */
final class TenantExists implements ValidationRule
{
    public function __construct(
        private readonly string $table,
        private readonly string $column = 'id',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $tenantId = app(TenantContext::class)->require()->id;

        $exists = DB::table($this->table)
            ->where($this->column, $value)
            ->where('tenant_id', $tenantId)
            ->exists();

        if (! $exists) {
            $fail('validation.exists')->translate(['attribute' => $attribute]);
        }
    }
}
