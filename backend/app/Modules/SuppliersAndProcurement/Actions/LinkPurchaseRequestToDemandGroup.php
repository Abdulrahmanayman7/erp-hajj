<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroup;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroupActiveMembership;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroupItem;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Support\PurchaseDemandGroupEngagementGuard;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class LinkPurchaseRequestToDemandGroup
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly PurchaseDemandGroupEngagementGuard $engagement,
    ) {}

    /**
     * @param  array{purchase_request_id: int}  $data
     * @return array{group: PurchaseDemandGroup, item: PurchaseDemandGroupItem, warnings: list<array<string, mixed>>}
     */
    public function execute(
        User $actor,
        PurchaseDemandGroup $group,
        array $data,
        Request $request,
    ): array {
        $tenant = $this->tenantContext->require();
        $prId = (int) $data['purchase_request_id'];

        // Engagement freeze commits in its own txn so a rejected mutate cannot roll it back.
        $this->engagement->assertMembershipEditableOrFreeze((int) $group->id, $actor, $request);

        return DB::transaction(function () use ($actor, $group, $prId, $request, $tenant): array {
            $locked = $this->engagement->lockGroup((int) $group->id);
            // Re-check under row lock (race with S05 engagement writer).
            $this->engagement->assertMembershipEditable($locked, $actor, $request);

            /** @var PurchaseRequest|null $pr */
            $pr = PurchaseRequest::query()->whereKey($prId)->lockForUpdate()->first();
            if ($pr === null) {
                throw SupplierDomainException::dgPrNotFound();
            }

            if ($pr->status !== PurchaseRequestStatus::Approved) {
                throw SupplierDomainException::dgPrNotApproved();
            }

            $item = new PurchaseDemandGroupItem;
            $item->forceFill([
                'demand_group_id' => $locked->id,
                'purchase_request_id' => $pr->id,
                'pr_version_no_at_link' => (int) $pr->version_no,
                'linked_at' => now(),
                'linked_by' => $actor->id,
            ]);
            $item->save();

            try {
                // Composite PK (tenant_id, purchase_request_id) — insert via query builder.
                DB::table('purchase_demand_group_active_memberships')->insert([
                    'tenant_id' => $tenant->id,
                    'purchase_request_id' => $pr->id,
                    'demand_group_id' => $locked->id,
                    'item_id' => $item->id,
                    'created_at' => now(),
                ]);
            } catch (QueryException $e) {
                if ($this->isDuplicateKey($e)) {
                    throw SupplierDomainException::dgPrAlreadyGrouped();
                }
                throw $e;
            }

            $this->security->record(AuthorizationSecurityEvent::DEMAND_GROUP_PR_LINKED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_demand_group',
                'entity_id' => $locked->id,
                'group_number' => $locked->group_number,
                'purchase_request_id' => $pr->id,
                'pr_number' => $pr->pr_number,
                'item_id' => $item->id,
            ], $request);

            $fresh = $locked->fresh(['creator', 'season', 'beneficiaryUnit', 'activeMemberships.purchaseRequest']) ?? $locked;

            return [
                'group' => $fresh,
                'item' => $item->fresh(['purchaseRequest']) ?? $item,
                'warnings' => $this->buildWarnings($fresh, $pr),
            ];
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildWarnings(PurchaseDemandGroup $group, PurchaseRequest $pr): array
    {
        $warnings = [];

        if ($group->season_id !== null && $pr->season_id !== null && (int) $group->season_id !== (int) $pr->season_id) {
            $warnings[] = [
                'code' => 'DG_MIXED_SEASON',
                'message' => 'موسم طلب الشراء يختلف عن موسم مجموعة التخطيط.',
            ];
        }

        if (
            $group->beneficiary_organization_unit_id !== null
            && $pr->beneficiary_organization_unit_id !== null
            && (int) $group->beneficiary_organization_unit_id !== (int) $pr->beneficiary_organization_unit_id
        ) {
            $warnings[] = [
                'code' => 'DG_MIXED_ORG_UNIT',
                'message' => 'الوحدة التنظيمية لطلب الشراء تختلف عن وحدة مجموعة التخطيط.',
            ];
        }

        $memberPrs = PurchaseRequest::query()
            ->whereIn(
                'id',
                PurchaseDemandGroupActiveMembership::query()
                    ->where('demand_group_id', $group->id)
                    ->pluck('purchase_request_id'),
            )
            ->get(['id', 'request_kind', 'season_id']);

        $hasSeasonal = $memberPrs->contains(fn (PurchaseRequest $m): bool => $m->season_id !== null);
        $hasAdmin = $memberPrs->contains(fn (PurchaseRequest $m): bool => $m->season_id === null);
        if ($hasSeasonal && $hasAdmin) {
            $warnings[] = [
                'code' => 'DG_MIXED_KIND',
                'message' => 'المجموعة تضم طلبات موسمية وإدارية معاً.',
            ];
        }

        return $warnings;
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        $code = (string) ($e->errorInfo[1] ?? '');
        $message = $e->getMessage();

        return $code === '1062' || str_contains($message, 'Duplicate') || str_contains($message, 'UNIQUE');
    }
}
