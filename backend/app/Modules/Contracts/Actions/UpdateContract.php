<?php

namespace App\Modules\Contracts\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Contracts\Enums\ContractStatus;
use App\Modules\Contracts\Enums\CounterpartyKind;
use App\Modules\Contracts\Exceptions\ContractDomainException;
use App\Modules\Contracts\Models\Contract;
use App\Modules\Contracts\Support\ContractReferenceValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateContract
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ContractReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Contract $contract, array $data, Request $request): Contract
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $contract, $data, $request, $tenant): Contract {
            $locked = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ContractStatus::Draft) {
                throw ContractDomainException::notEditable();
            }

            $categoryId = array_key_exists('contract_category_id', $data)
                ? (int) $data['contract_category_id']
                : (int) $locked->contract_category_id;
            $category = $this->references->resolveAssignableCategory($categoryId, (int) $locked->contract_category_id);

            $employeeId = array_key_exists('employee_id', $data)
                ? ($data['employee_id'] !== null ? (int) $data['employee_id'] : null)
                : $locked->employee_id;
            $employee = $this->references->resolveAssignableEmployee($employeeId, $locked->employee_id);

            $unitId = array_key_exists('organization_unit_id', $data)
                ? ($data['organization_unit_id'] !== null ? (int) $data['organization_unit_id'] : null)
                : $locked->organization_unit_id;
            $unit = $this->references->resolveAssignableOrganizationUnit($unitId, $locked->organization_unit_id);

            $start = $data['start_date'] ?? $locked->start_date->toDateString();
            $end = array_key_exists('end_date', $data) ? $data['end_date'] : $locked->end_date?->toDateString();
            $this->references->assertDateRange($start, $end);

            if (array_key_exists('title', $data)) {
                $locked->title = $data['title'];
            }
            $locked->contract_category_id = $category->id;
            if (array_key_exists('counterparty_name', $data)) {
                $locked->counterparty_name = $data['counterparty_name'];
            }
            if (array_key_exists('counterparty_kind', $data)) {
                $locked->counterparty_kind = CounterpartyKind::from($data['counterparty_kind']);
            }
            $locked->employee_id = $employee?->id;
            $locked->organization_unit_id = $unit?->id;
            $locked->start_date = $start;
            $locked->end_date = $end;
            if (array_key_exists('value', $data)) {
                $locked->value = $data['value'];
            }
            if (array_key_exists('currency', $data) && $data['currency'] !== null) {
                $locked->currency = strtoupper((string) $data['currency']);
            }
            if (array_key_exists('notes', $data)) {
                $locked->notes = $data['notes'];
            }

            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::CONTRACT_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'contract_id' => $locked->id,
                'contract_number' => $locked->contract_number,
            ], $request);

            return $locked->load(['category', 'employee', 'organizationUnit', 'createdBy', 'statusTransitions.actor']);
        });
    }
}
