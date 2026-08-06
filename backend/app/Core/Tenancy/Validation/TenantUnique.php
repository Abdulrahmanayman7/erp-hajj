<?php

namespace App\Core\Tenancy\Validation;

use App\Core\Tenancy\TenantContext;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Tenant-scoped "unique" rule: uniqueness is checked within the current
 * tenant only, so two tenants may hold the same value. The ignore clause
 * (for updates) also stays tenant-scoped — an ID from another tenant cannot
 * be used to skip the check.
 */
final class TenantUnique implements ValidationRule
{
    private ?int $ignoreId = null;

    private string $ignoreColumn = 'id';

    public function __construct(
        private readonly string $table,
        private readonly ?string $column = null,
    ) {}

    public function ignore(int $id, string $idColumn = 'id'): self
    {
        $this->ignoreId = $id;
        $this->ignoreColumn = $idColumn;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $tenantId = app(TenantContext::class)->require()->id;

        $query = DB::table($this->table)
            ->where($this->column ?? $attribute, $value)
            ->where('tenant_id', $tenantId);

        if ($this->ignoreId !== null) {
            $query->where($this->ignoreColumn, '!=', $this->ignoreId);
        }

        if ($query->exists()) {
            $fail('validation.unique')->translate(['attribute' => $attribute]);
        }
    }
}
