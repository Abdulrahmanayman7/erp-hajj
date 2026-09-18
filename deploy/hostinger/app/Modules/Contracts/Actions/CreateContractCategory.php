<?php

namespace App\Modules\Contracts\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Contracts\Exceptions\ContractDomainException;
use App\Modules\Contracts\Models\ContractCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateContractCategory
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{name: string, code?: string|null, is_active?: bool|null}  $data
     */
    public function execute(User $actor, array $data, Request $request): ContractCategory
    {
        $tenant = $this->tenantContext->require();
        $code = $this->normalizeCode($data['code'] ?? null);

        return DB::transaction(function () use ($actor, $data, $request, $tenant, $code): ContractCategory {
            if (ContractCategory::query()->where('name', $data['name'])->exists()) {
                throw ContractDomainException::categoryNameTaken();
            }

            if ($code !== null && ContractCategory::query()->where('code', $code)->exists()) {
                throw ContractDomainException::categoryCodeTaken();
            }

            $category = ContractCategory::query()->create([
                'name' => $data['name'],
                'code' => $code,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            ]);

            $this->security->record(AuthorizationSecurityEvent::CONTRACT_CATEGORY_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'contract_category_id' => $category->id,
                'name' => $category->name,
                'code' => $category->code,
            ], $request);

            return $category;
        });
    }

    private function normalizeCode(mixed $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $trimmed = trim((string) $code);

        return $trimmed === '' ? null : $trimmed;
    }
}
