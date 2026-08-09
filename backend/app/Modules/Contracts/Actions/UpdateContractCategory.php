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

final class UpdateContractCategory
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{name?: string, code?: string|null, is_active?: bool|null}  $data
     */
    public function execute(User $actor, ContractCategory $category, array $data, Request $request): ContractCategory
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $category, $data, $request, $tenant): ContractCategory {
            $locked = ContractCategory::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();

            if (array_key_exists('code', $data)) {
                $incoming = $data['code'];
                $normalized = $incoming === null || trim((string) $incoming) === ''
                    ? null
                    : trim((string) $incoming);

                if ($normalized !== $locked->code) {
                    throw ContractDomainException::categoryCodeImmutable();
                }
            }

            if (array_key_exists('name', $data)) {
                if (ContractCategory::query()->where('name', $data['name'])->whereKeyNot($locked->id)->exists()) {
                    throw ContractDomainException::categoryNameTaken();
                }
                $locked->name = $data['name'];
            }

            if (array_key_exists('is_active', $data) && $data['is_active'] !== null) {
                $locked->is_active = (bool) $data['is_active'];
            }

            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::CONTRACT_CATEGORY_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'contract_category_id' => $locked->id,
                'name' => $locked->name,
            ], $request);

            return $locked;
        });
    }
}
