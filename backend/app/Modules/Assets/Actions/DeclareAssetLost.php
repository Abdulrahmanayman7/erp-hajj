<?php

namespace App\Modules\Assets\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Enums\CustodyStatus;
use App\Modules\Assets\Exceptions\AssetDomainException;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\AssetCustody;
use App\Modules\Assets\Support\AssetReferenceValidator;
use App\Modules\Assets\Support\AssetStatusTransitionRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeclareAssetLost
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AssetReferenceValidator $references,
        private readonly AssetStatusTransitionRecorder $transitions,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Asset $asset, array $data, Request $request): Asset
    {
        $tenant = $this->tenantContext->require();
        $reason = $this->references->assertRetireReason($data['reason'] ?? null);

        return DB::transaction(function () use ($actor, $asset, $reason, $request, $tenant): Asset {
            /** @var Asset $locked */
            $locked = Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();

            $allowed = [
                AssetStatus::Available,
                AssetStatus::Maintenance,
                AssetStatus::Damaged,
                AssetStatus::InUse,
            ];
            if (! in_array($locked->status, $allowed, true)) {
                throw AssetDomainException::invalidStatusTransition();
            }

            $custodyId = null;
            if ($locked->status === AssetStatus::InUse) {
                if ($locked->current_custody_id === null) {
                    throw AssetDomainException::notAssigned();
                }

                /** @var AssetCustody $custody */
                $custody = AssetCustody::query()
                    ->whereKey($locked->current_custody_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($custody->status !== CustodyStatus::Active) {
                    throw AssetDomainException::notAssigned();
                }

                $custody->status = CustodyStatus::Returned;
                $custody->returned_at = now();
                $custody->returned_by = $actor->id;
                $custody->return_notes = $reason;
                $custody->save();
                $custodyId = (int) $custody->id;
                $locked->current_custody_id = null;
            }

            $from = $locked->status;
            $locked->status = AssetStatus::Lost;
            $locked->save();

            $this->transitions->record($locked, $from, AssetStatus::Lost, $actor, $reason, $custodyId);
            $this->security->record(AuthorizationSecurityEvent::ASSET_DECLARED_LOST, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'asset_id' => $locked->id,
                'asset_number' => $locked->asset_number,
                'from_status' => $from->value,
                'to_status' => AssetStatus::Lost->value,
                'reason' => $reason,
                'custody_id' => $custodyId,
            ], $request);

            return $locked->load(['category', 'warehouse', 'organizationUnit', 'currentCustody.employee', 'createdBy']);
        });
    }
}
