<?php

namespace App\Modules\Contracts\Resources;

use App\Core\Tenancy\TenantContext;
use App\Modules\Contracts\Models\Contract;
use App\Modules\Contracts\Models\ContractStatusTransition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin Contract
 */
class ContractResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Contract $contract */
        $contract = $this->resource;

        $payload = [
            'id' => $contract->id,
            'contract_number' => $contract->contract_number,
            'title' => $contract->title,
            'status' => $contract->status->value,
            'counterparty_name' => $contract->counterparty_name,
            'counterparty_kind' => $contract->counterparty_kind->value,
            'start_date' => $contract->start_date?->format('Y-m-d'),
            'end_date' => $contract->end_date?->format('Y-m-d'),
            'value' => $contract->value,
            'currency' => $contract->currency,
            'notes' => $contract->notes,
            'is_expiring_soon' => $contract->isExpiringSoon($this->tenantToday()),
            'category' => $this->categoryPayload($contract),
            'employee' => $this->employeePayload($contract),
            'organization_unit' => $this->organizationUnitPayload($contract),
            'created_by' => $this->createdByPayload($contract),
            'renewed_from_contract_id' => $contract->renewed_from_contract_id,
            'created_at' => $contract->created_at?->toIso8601String(),
            'updated_at' => $contract->updated_at?->toIso8601String(),
        ];

        if ($contract->relationLoaded('renewalChild')) {
            $payload['renewal_child'] = $contract->renewalChild === null ? null : [
                'id' => $contract->renewalChild->id,
                'contract_number' => $contract->renewalChild->contract_number,
                'status' => $contract->renewalChild->status->value,
            ];
        }

        if ($contract->relationLoaded('statusTransitions')) {
            $payload['transitions'] = $contract->statusTransitions
                ->map(fn (ContractStatusTransition $row): array => $this->transitionPayload($row))
                ->values()
                ->all();
        }

        return $payload;
    }

    private function tenantToday(): Carbon
    {
        $tenant = app(TenantContext::class)->get();
        $timezone = $tenant?->timezone ?: config('app.timezone', 'Asia/Riyadh');

        return now($timezone)->startOfDay();
    }

    /**
     * @return array{id: int, name: string, code: string|null}|null
     */
    private function categoryPayload(Contract $contract): ?array
    {
        if (! $contract->relationLoaded('category') || $contract->category === null) {
            return null;
        }

        return [
            'id' => $contract->category->id,
            'name' => $contract->category->name,
            'code' => $contract->category->code,
        ];
    }

    /**
     * @return array{id: int, employee_number: string, full_name: string}|null
     */
    private function employeePayload(Contract $contract): ?array
    {
        if (! $contract->relationLoaded('employee') || $contract->employee === null) {
            return null;
        }

        return [
            'id' => $contract->employee->id,
            'employee_number' => $contract->employee->employee_number,
            'full_name' => $contract->employee->full_name,
        ];
    }

    /**
     * @return array{id: int, name: string, code: string}|null
     */
    private function organizationUnitPayload(Contract $contract): ?array
    {
        if (! $contract->relationLoaded('organizationUnit') || $contract->organizationUnit === null) {
            return null;
        }

        return [
            'id' => $contract->organizationUnit->id,
            'name' => $contract->organizationUnit->name,
            'code' => $contract->organizationUnit->code,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function createdByPayload(Contract $contract): ?array
    {
        if (! $contract->relationLoaded('createdBy') || $contract->createdBy === null) {
            return null;
        }

        return [
            'id' => $contract->createdBy->id,
            'name' => $contract->createdBy->name,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     from_status: string|null,
     *     to_status: string,
     *     comment: string|null,
     *     actor: array{id: int, name: string}|null,
     *     correlation_id: string|null,
     *     created_at: string|null
     * }
     */
    private function transitionPayload(ContractStatusTransition $row): array
    {
        $actor = null;
        if ($row->relationLoaded('actor') && $row->actor !== null) {
            $actor = [
                'id' => $row->actor->id,
                'name' => $row->actor->name,
            ];
        }

        return [
            'id' => $row->id,
            'from_status' => $row->from_status?->value,
            'to_status' => $row->to_status instanceof \BackedEnum
                ? $row->to_status->value
                : (string) $row->to_status,
            'comment' => $row->comment,
            'actor' => $actor,
            'correlation_id' => $row->correlation_id,
            'created_at' => $row->created_at?->toIso8601String(),
        ];
    }
}
