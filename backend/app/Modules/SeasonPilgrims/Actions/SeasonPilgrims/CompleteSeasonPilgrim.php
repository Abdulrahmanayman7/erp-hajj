<?php

namespace App\Modules\SeasonPilgrims\Actions\SeasonPilgrims;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Enums\SeasonPilgrimStatus;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Active → Completed. The snapshot is not regenerated (F20).
 */
final class CompleteSeasonPilgrim
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, SeasonPilgrim $seasonPilgrim, Request $request): SeasonPilgrim
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $seasonPilgrim, $request, $tenant): SeasonPilgrim {
            /** @var SeasonPilgrim $locked */
            $locked = SeasonPilgrim::query()->whereKey($seasonPilgrim->id)->lockForUpdate()->firstOrFail();
            SeasonGuard::assertWritable($locked->season()->firstOrFail());

            if ($locked->status !== SeasonPilgrimStatus::Active) {
                throw SeasonPilgrimDomainException::seasonPilgrimInvalidTransition();
            }

            $locked->update([
                'status' => SeasonPilgrimStatus::Completed,
                'completed_at' => now(),
                'completed_by' => $actor->id,
            ]);

            $this->security->record(AuthorizationSecurityEvent::SEASON_PILGRIM_COMPLETED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_pilgrim_id' => $locked->id,
                'reference_number' => $locked->reference_number,
                'from_status' => SeasonPilgrimStatus::Active->value,
                'to_status' => SeasonPilgrimStatus::Completed->value,
                'metadata' => ['season_id' => $locked->season_id],
            ], $request);

            return SeasonPilgrimLoader::load($locked);
        });
    }
}
