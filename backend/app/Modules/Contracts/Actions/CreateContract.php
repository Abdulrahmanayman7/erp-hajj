<?php

namespace App\Modules\Contracts\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Contracts\Enums\ContractStatus;
use App\Modules\Contracts\Enums\CounterpartyKind;
use App\Modules\Contracts\Models\Contract;
use App\Modules\Contracts\Support\ContractNumberGenerator;
use App\Modules\Contracts\Support\ContractReferenceValidator;
use App\Modules\Contracts\Support\ContractTransitionRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateContract
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ContractNumberGenerator $numbers,
        private readonly ContractReferenceValidator $references,
        private readonly ContractTransitionRecorder $transitions,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data, Request $request): Contract
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $data, $request, $tenant): Contract {
            $category = $this->references->resolveAssignableCategory((int) $data['contract_category_id']);
            $employee = $this->references->resolveAssignableEmployee(
                array_key_exists('employee_id', $data) && $data['employee_id'] !== null
                    ? (int) $data['employee_id']
                    : null,
            );
            $unit = $this->references->resolveAssignableOrganizationUnit(
                array_key_exists('organization_unit_id', $data) && $data['organization_unit_id'] !== null
                    ? (int) $data['organization_unit_id']
                    : null,
            );

            $this->references->assertDateRange($data['start_date'], $data['end_date'] ?? null);

            $kind = $data['counterparty_kind'] ?? CounterpartyKind::Organization->value;
            $currency = strtoupper((string) ($data['currency'] ?? config('contracts.default_currency', 'SAR')));

            $contract = new Contract([
                'title' => $data['title'],
                'contract_category_id' => $category->id,
                'status' => ContractStatus::Draft,
                'counterparty_name' => $data['counterparty_name'],
                'counterparty_kind' => CounterpartyKind::from($kind),
                'employee_id' => $employee?->id,
                'organization_unit_id' => $unit?->id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'value' => $data['value'] ?? null,
                'currency' => $currency,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);
            $contract->contract_number = $this->numbers->next();
            $contract->save();

            $this->transitions->record($contract, null, ContractStatus::Draft, $actor, null, $request);

            $this->security->record(AuthorizationSecurityEvent::CONTRACT_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'contract_id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'status' => $contract->status->value,
            ], $request);

            return $contract->load(['category', 'employee', 'organizationUnit', 'createdBy', 'statusTransitions.actor']);
        });
    }
}
