<?php

namespace App\Modules\SeasonPilgrims\Actions\SeasonPilgrims;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimIdentitySnapshot;
use App\Modules\SeasonPilgrims\Support\IdentityMasking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * F20 historical correction of a LOCKED snapshot: explicit values,
 * dedicated permission, mandatory reason, audited before/after. The
 * current Pilgrim record is not changed.
 *
 * This is the only write allowed on a Closed season: it changes the
 * snapshot row alone, never reopens the season, and never re-reads current
 * Pilgrim data.
 */
final class CorrectSeasonPilgrimSnapshot
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public function execute(User $actor, SeasonPilgrim $seasonPilgrim, array $values, string $reason, Request $request): SeasonPilgrim
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $seasonPilgrim, $values, $reason, $request, $tenant): SeasonPilgrim {
            /** @var SeasonPilgrim $locked */
            $locked = SeasonPilgrim::query()->whereKey($seasonPilgrim->id)->lockForUpdate()->firstOrFail();
            /** @var Season $season */
            $season = $locked->season()->firstOrFail();

            /** @var SeasonPilgrimIdentitySnapshot|null $snapshot */
            $snapshot = $locked->snapshot()->lockForUpdate()->first();
            if ($snapshot === null || ! $snapshot->isLocked()) {
                throw SeasonPilgrimDomainException::snapshotNotLocked();
            }

            $changes = array_intersect_key($values, array_flip(SeasonPilgrimIdentitySnapshot::IDENTITY_FIELDS));
            foreach (['nationality', 'document_issuing_country'] as $upper) {
                if (isset($changes[$upper])) {
                    $changes[$upper] = mb_strtoupper((string) $changes[$upper]);
                }
            }

            $before = IdentityMasking::maskValues($snapshot->identityValues());
            $snapshot->update([
                ...$changes,
                'corrected_at' => now(),
                'corrected_by' => $actor->id,
                'correction_count' => $snapshot->correction_count + 1,
            ]);
            $after = IdentityMasking::maskValues($snapshot->identityValues());

            $this->security->record(AuthorizationSecurityEvent::SEASON_PILGRIM_SNAPSHOT_CORRECTED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_pilgrim_id' => $locked->id,
                'reference_number' => $locked->reference_number,
                'pilgrim_name' => $snapshot->full_name,
                'reason' => $reason,
                'before_values' => array_diff_assoc($before, $after),
                'after_values' => array_diff_assoc($after, $before),
                'metadata' => [
                    'season_id' => $locked->season_id,
                    'season_status' => $season->status->value,
                    'season_pilgrim_status' => $locked->status->value,
                    'correction_count' => $snapshot->correction_count,
                ],
            ], $request);

            return SeasonPilgrimLoader::load($locked);
        });
    }
}
