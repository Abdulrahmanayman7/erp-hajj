<?php

namespace App\Modules\SeasonProjects\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonProjects\Exceptions\SeasonProjectDomainException;
use App\Modules\SeasonProjects\Models\SeasonProject;
use App\Modules\SeasonProjects\Models\SeasonProjectBudgetPoLink;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseOrderStatus;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * E-Q06 header link; E-Q04 same-tenant any season; E-Q09 currency match required.
 */
final class LinkSeasonProjectPurchaseOrder
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly GetSeasonProjectBudget $reader,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(
        User $actor,
        SeasonProject $project,
        int $purchaseOrderId,
        ?string $notes,
        Request $request,
    ): array {
        $tenant = $this->tenantContext->require();

        try {
            return DB::transaction(function () use ($actor, $project, $purchaseOrderId, $notes, $request, $tenant): array {
                /** @var SeasonProject $locked */
                $locked = SeasonProject::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();

                // Terminal projects cannot gain new PO links (completed or cancelled).
                if ($locked->status->isTerminal()) {
                    throw SeasonProjectDomainException::poNotLinkable();
                }

                /** @var PurchaseOrder|null $po */
                $po = PurchaseOrder::query()->whereKey($purchaseOrderId)->lockForUpdate()->first();
                if ($po === null) {
                    abort(404);
                }

                if (! in_array($po->status, [
                    PurchaseOrderStatus::Issued,
                    PurchaseOrderStatus::PartiallyReceived,
                    PurchaseOrderStatus::Received,
                    PurchaseOrderStatus::Closed,
                ], true)) {
                    throw SeasonProjectDomainException::poNotLinkable();
                }

                if (strtoupper((string) $po->currency) !== strtoupper((string) $locked->currency_code)) {
                    throw SeasonProjectDomainException::poCurrencyMismatch();
                }

                $existing = SeasonProjectBudgetPoLink::query()
                    ->where('purchase_order_id', $po->id)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    throw SeasonProjectDomainException::poAlreadyLinked();
                }

                $link = new SeasonProjectBudgetPoLink;
                $link->forceFill([
                    'season_id' => $locked->season_id,
                    'project_id' => $locked->id,
                    'purchase_order_id' => $po->id,
                    'commitment_amount' => null,
                    'actual_amount' => null,
                    'currency_code' => strtoupper((string) $locked->currency_code),
                    'linked_at' => now(),
                    'linked_by' => $actor->id,
                    'notes' => $notes,
                ]);
                $link->save();

                $this->security->record(AuthorizationSecurityEvent::SEASON_PROJECT_PO_LINKED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'season_id' => $locked->season_id,
                    'project_id' => $locked->id,
                    'purchase_order_id' => $po->id,
                    'po_number' => $po->po_number,
                    'po_season_id' => $po->season_id,
                    'link_id' => $link->id,
                ], $request);

                return $this->reader->execute($locked->fresh() ?? $locked);
            });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'season_project_budget_po_links_tenant_po_unique')) {
                throw SeasonProjectDomainException::poAlreadyLinked();
            }
            throw $e;
        }
    }
}
