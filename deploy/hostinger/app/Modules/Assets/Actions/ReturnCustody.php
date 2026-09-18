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
use App\Modules\Notifications\Support\NotificationHooks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ReturnCustody
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AssetReferenceValidator $references,
        private readonly AssetStatusTransitionRecorder $transitions,
        private readonly AuthorizationSecurity $security,
        private readonly NotificationHooks $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Asset $asset, array $data, Request $request): Asset
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $asset, $data, $request, $tenant): Asset {
            /** @var Asset $locked */
            $locked = Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== AssetStatus::InUse || $locked->current_custody_id === null) {
                throw AssetDomainException::notAssigned();
            }

            /** @var AssetCustody|null $custody */
            $custody = AssetCustody::query()
                ->whereKey($locked->current_custody_id)
                ->lockForUpdate()
                ->first();

            if ($custody === null || $custody->status !== CustodyStatus::Active) {
                throw AssetDomainException::notAssigned();
            }

            if ((int) $custody->asset_id !== (int) $locked->id) {
                throw AssetDomainException::custodyConflict();
            }

            $next = $this->references->assertReturnNextStatus((string) $data['next_status']);
            $condition = $this->references->assertCondition(
                isset($data['condition_at_return']) ? (string) $data['condition_at_return'] : null,
            );

            $custody->status = CustodyStatus::Returned;
            $custody->returned_at = now();
            $custody->returned_by = $actor->id;
            $custody->condition_at_return = $condition;
            $custody->return_notes = isset($data['return_notes']) ? (string) $data['return_notes'] : null;
            $custody->save();

            $from = $locked->status;
            $locked->status = $next;
            $locked->current_custody_id = null;
            $locked->save();

            $this->transitions->record(
                $locked,
                $from,
                $next,
                $actor,
                null,
                (int) $custody->id,
            );

            $this->security->record(AuthorizationSecurityEvent::ASSET_RETURNED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'asset_id' => $locked->id,
                'asset_number' => $locked->asset_number,
                'custody_id' => $custody->id,
                'custody_number' => $custody->custody_number,
                'from_status' => $from->value,
                'to_status' => $next->value,
            ], $request);

            $this->notifications->custodyReturned($custody, $locked);

            return $locked->load([
                'category',
                'warehouse',
                'organizationUnit',
                'currentCustody.employee',
                'createdBy',
            ]);
        });
    }
}
