<?php

namespace App\Modules\Assets\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Exceptions\AssetDomainException;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Support\AssetStatusTransitionRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RestoreAsset
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AssetStatusTransitionRecorder $transitions,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Asset $asset, Request $request): Asset
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $asset, $request, $tenant): Asset {
            /** @var Asset $locked */
            $locked = Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [AssetStatus::Maintenance, AssetStatus::Damaged], true)) {
                throw AssetDomainException::invalidStatusTransition();
            }

            $from = $locked->status;
            $locked->status = AssetStatus::Available;
            $locked->save();

            $this->transitions->record($locked, $from, AssetStatus::Available, $actor);
            $this->security->record(AuthorizationSecurityEvent::ASSET_RESTORED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'asset_id' => $locked->id,
                'asset_number' => $locked->asset_number,
                'from_status' => $from->value,
                'to_status' => AssetStatus::Available->value,
            ], $request);

            return $locked->load(['category', 'warehouse', 'organizationUnit', 'currentCustody.employee', 'createdBy']);
        });
    }
}
