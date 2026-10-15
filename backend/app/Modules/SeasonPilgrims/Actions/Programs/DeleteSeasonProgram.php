<?php

namespace App\Modules\SeasonPilgrims\Actions\Programs;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\SeasonProgram;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\SeasonPilgrims\Support\SeasonResourceDeletionReferences;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * F25: only an entirely unused program (no current assignment, no history)
 * may be hard-deleted.
 */
final class DeleteSeasonProgram
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly SeasonResourceDeletionReferences $deletionReferences,
    ) {}

    public function execute(User $actor, SeasonProgram $program, Request $request): void
    {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $program, $request, $tenant): void {
            /** @var SeasonProgram $locked */
            $locked = SeasonProgram::query()->whereKey($program->id)->lockForUpdate()->firstOrFail();
            SeasonGuard::assertWritable($locked->season()->firstOrFail());

            if ($locked->isUsed()) {
                throw SeasonPilgrimDomainException::programInUse();
            }

            $this->deletionReferences->assertDeletable($locked);

            $context = [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_program_id' => $locked->id,
                'season_program_code' => $locked->code,
                'season_program_name' => $locked->name,
                'metadata' => ['season_id' => $locked->season_id],
            ];

            $locked->delete();

            $this->security->record(AuthorizationSecurityEvent::SEASON_PROGRAM_DELETED, $context, $request);
        });
    }
}
