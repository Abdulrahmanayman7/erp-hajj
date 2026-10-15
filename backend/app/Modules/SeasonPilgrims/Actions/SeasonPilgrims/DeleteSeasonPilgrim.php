<?php

namespace App\Modules\SeasonPilgrims\Actions\SeasonPilgrims;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimIdentitySnapshot;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimProgramChange;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\SeasonPilgrims\Support\SeasonPilgrimOperationalReferences;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * F7 mistaken-registration delete: only when never operationally
 * referenced. The reference number is not reused (sequence never rewinds).
 */
final class DeleteSeasonPilgrim
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SeasonPilgrimOperationalReferences $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, SeasonPilgrim $seasonPilgrim, Request $request): void
    {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $seasonPilgrim, $request, $tenant): void {
            /** @var SeasonPilgrim $locked */
            $locked = SeasonPilgrim::query()->whereKey($seasonPilgrim->id)->lockForUpdate()->firstOrFail();
            SeasonGuard::assertWritable($locked->season()->firstOrFail());

            if ($this->references->isReferenced($locked)) {
                throw SeasonPilgrimDomainException::seasonPilgrimDeleteForbidden();
            }

            $context = [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_pilgrim_id' => $locked->id,
                'reference_number' => $locked->reference_number,
                'from_status' => $locked->status->value,
                'metadata' => [
                    'season_id' => $locked->season_id,
                    'pilgrim_id' => $locked->pilgrim_id,
                    'season_family_group_id' => $locked->season_family_group_id,
                    'was_family_primary' => (bool) $locked->is_family_primary,
                ],
            ];

            SeasonPilgrimProgramChange::query()->where('season_pilgrim_id', $locked->id)->delete();
            SeasonPilgrimIdentitySnapshot::query()->where('season_pilgrim_id', $locked->id)->delete();
            $locked->delete();

            $this->security->record(AuthorizationSecurityEvent::SEASON_PILGRIM_DELETED, $context, $request);
        });
    }
}
