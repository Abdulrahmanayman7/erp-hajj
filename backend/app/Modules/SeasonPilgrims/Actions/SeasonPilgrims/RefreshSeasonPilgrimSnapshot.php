<?php

namespace App\Modules\SeasonPilgrims\Actions\SeasonPilgrims;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimIdentitySnapshot;
use App\Modules\SeasonPilgrims\Support\IdentityMasking;
use App\Modules\SeasonPilgrims\Support\IdentitySnapshotCapturer;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * F20: explicit refresh from current Pilgrim data while Registered and the
 * snapshot is not locked.
 */
final class RefreshSeasonPilgrimSnapshot
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly IdentitySnapshotCapturer $capturer,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, SeasonPilgrim $seasonPilgrim, ?int $documentId, Request $request): SeasonPilgrim
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $seasonPilgrim, $documentId, $request, $tenant): SeasonPilgrim {
            /** @var SeasonPilgrim $locked */
            $locked = SeasonPilgrim::query()->whereKey($seasonPilgrim->id)->lockForUpdate()->firstOrFail();
            SeasonGuard::assertWritable($locked->season()->firstOrFail());

            /** @var SeasonPilgrimIdentitySnapshot|null $current */
            $current = $locked->snapshot()->lockForUpdate()->first();
            if ($current?->isLocked()) {
                throw SeasonPilgrimDomainException::snapshotLocked();
            }
            SeasonGuard::assertRegistered($locked);

            $before = IdentityMasking::maskValues($current?->identityValues() ?? []);
            $snapshot = $this->capturer->capture($locked, $documentId, true, $actor->id);
            $after = IdentityMasking::maskValues($snapshot->identityValues());

            $this->security->record(AuthorizationSecurityEvent::SEASON_PILGRIM_SNAPSHOT_REFRESHED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_pilgrim_id' => $locked->id,
                'reference_number' => $locked->reference_number,
                'pilgrim_name' => $snapshot->full_name,
                'before_values' => array_diff_assoc($before, $after),
                'after_values' => array_diff_assoc($after, $before),
                'metadata' => ['season_id' => $locked->season_id],
            ], $request);

            return SeasonPilgrimLoader::load($locked);
        });
    }
}
