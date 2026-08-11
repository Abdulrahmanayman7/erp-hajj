<?php

namespace App\Modules\Assets\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Exceptions\AssetDomainException;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\AssetStatusTransition;
use App\Modules\Documents\Enums\DocumentLinkableType;
use App\Modules\Documents\Support\DocumentReferenceValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeleteAsset
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly DocumentReferenceValidator $documents,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Asset $asset, Request $request): void
    {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $asset, $request, $tenant): void {
            /** @var Asset $locked */
            $locked = Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === AssetStatus::InUse || $locked->current_custody_id !== null) {
                throw AssetDomainException::inUse();
            }

            if ($locked->custodies()->exists()) {
                throw AssetDomainException::inUse();
            }

            $transitionCount = $locked->transitions()->count();
            $onlyCreate = $transitionCount === 1
                && $locked->transitions()
                    ->whereNull('from_status')
                    ->where('to_status', AssetStatus::Available->value)
                    ->exists();

            if ($transitionCount > 1 || ($transitionCount === 1 && ! $onlyCreate)) {
                throw AssetDomainException::inUse();
            }

            $this->documents->assertNoDocumentsLinked(DocumentLinkableType::Asset, (int) $locked->id);

            $assetNumber = $locked->asset_number;
            $assetId = $locked->id;

            AssetStatusTransition::withoutEvents(function () use ($locked): void {
                AssetStatusTransition::query()->where('asset_id', $locked->id)->delete();
            });

            $locked->delete();

            $this->security->record(AuthorizationSecurityEvent::ASSET_DELETED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'asset_id' => $assetId,
                'asset_number' => $assetNumber,
            ], $request);
        });
    }
}
