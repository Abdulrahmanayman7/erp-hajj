<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestKind;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestPriority;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Support\PurchaseRequestPreconditions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdatePurchaseRequest
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly PurchaseRequestPreconditions $preconditions,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, PurchaseRequest $pr, array $data, Request $request): PurchaseRequest
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $pr, $data, $request, $tenant): PurchaseRequest {
            /** @var PurchaseRequest $locked */
            $locked = PurchaseRequest::query()->whereKey($pr->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isEditable()) {
                throw SupplierDomainException::prEditLocked();
            }

            $kind = isset($data['request_kind'])
                ? PurchaseRequestKind::from((string) $data['request_kind'])
                : $locked->request_kind;

            $seasonId = array_key_exists('season_id', $data)
                ? ($data['season_id'] !== null ? (int) $data['season_id'] : null)
                : $locked->season_id;

            $season = $this->preconditions->resolveSeason($kind, $seasonId);

            if (array_key_exists('beneficiary_organization_unit_id', $data)) {
                $unit = $this->preconditions->resolveOrgUnit((int) $data['beneficiary_organization_unit_id']);
                $locked->forceFill(['beneficiary_organization_unit_id' => $unit->id]);
            }

            if (array_key_exists('optional_supplier_id', $data)) {
                $supplier = $this->preconditions->resolveOptionalSupplier(
                    $data['optional_supplier_id'] !== null ? (int) $data['optional_supplier_id'] : null,
                );
                $locked->forceFill(['optional_supplier_id' => $supplier?->id]);
            }

            $priority = isset($data['priority'])
                ? PurchaseRequestPriority::from((string) $data['priority'])
                : $locked->priority;

            $justification = array_key_exists('urgency_justification', $data)
                ? $data['urgency_justification']
                : $locked->urgency_justification;

            $this->preconditions->assertPriority($priority, $justification !== null ? (string) $justification : null);

            if (isset($data['title'])) {
                $locked->title = (string) $data['title'];
            }
            if (isset($data['purpose'])) {
                $locked->purpose = (string) $data['purpose'];
            }

            $locked->forceFill([
                'request_kind' => $kind,
                'season_id' => $kind->requiresSeason() ? $season?->id : null,
                'priority' => $priority,
                'urgency_justification' => $priority->requiresJustification() ? $justification : null,
            ]);
            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::PR_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_request',
                'entity_id' => $locked->id,
                'pr_number' => $locked->pr_number,
            ], $request);

            return $locked->fresh(['lines', 'requester', 'beneficiaryUnit', 'optionalSupplier', 'season']) ?? $locked;
        });
    }
}
