<?php

namespace App\Modules\SeasonProjects\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonProjects\Exceptions\SeasonProjectDomainException;
use App\Modules\SeasonProjects\Models\SeasonProject;
use App\Modules\SeasonProjects\Models\SeasonProjectBudgetPoLink;
use App\Modules\SuppliersAndProcurement\Enums\GoodsReceiptStatus;
use App\Modules\SuppliersAndProcurement\Models\GoodsReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Removes association only; PO and GRN records are preserved.
 * Allowed while project is draft/active (not terminal) so soft-delete can clear links.
 *
 * F-Q04=A (owner-approved): once the linked PO has any posted GRN, unlink is forbidden
 * through ordinary project/PO-link operations for all roles (including tenant_owner /
 * link_po). Rejects with SEASON_PROJECT_PO_UNLINK_HAS_POSTED_RECEIPTS. A future financial
 * correction requires a separately approved adjustment workflow (out of S06).
 */
final class UnlinkSeasonProjectPurchaseOrder
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
        SeasonProjectBudgetPoLink $link,
        Request $request,
    ): array {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $project, $link, $request, $tenant): array {
            /** @var SeasonProject $lockedProject */
            $lockedProject = SeasonProject::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();

            /** @var SeasonProjectBudgetPoLink|null $locked */
            $locked = SeasonProjectBudgetPoLink::query()
                ->whereKey($link->id)
                ->where('project_id', $lockedProject->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                abort(404);
            }

            if ($lockedProject->status->isTerminal()) {
                throw SeasonProjectDomainException::poUnlinkNotAllowed();
            }

            $poId = (int) $locked->purchase_order_id;

            $hasPostedReceipt = GoodsReceipt::query()
                ->where('purchase_order_id', $poId)
                ->where('status', GoodsReceiptStatus::Posted->value)
                ->exists();

            if ($hasPostedReceipt) {
                throw SeasonProjectDomainException::poUnlinkHasPostedReceipts();
            }

            $linkId = (int) $locked->id;
            $locked->delete();

            $this->security->record(AuthorizationSecurityEvent::SEASON_PROJECT_PO_UNLINKED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_id' => $lockedProject->season_id,
                'project_id' => $lockedProject->id,
                'purchase_order_id' => $poId,
                'link_id' => $linkId,
                'source_records_preserved' => true,
            ], $request);

            return $this->reader->execute($lockedProject->fresh() ?? $lockedProject);
        });
    }
}
