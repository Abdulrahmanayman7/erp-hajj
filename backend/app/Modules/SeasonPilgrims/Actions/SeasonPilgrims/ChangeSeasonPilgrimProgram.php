<?php

namespace App\Modules\SeasonPilgrims\Actions\SeasonPilgrims;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Shared\CorrelationId;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Enums\SeasonPilgrimStatus;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimProgramChange;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\SeasonPilgrims\Support\SeasonReferenceResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * F22/F24: one current program per SeasonPilgrim; every change appends a
 * history row and is audited. Never touches other domains' assignments.
 */
final class ChangeSeasonPilgrimProgram
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SeasonReferenceResolver $references,
        private readonly CorrelationId $correlationId,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, SeasonPilgrim $seasonPilgrim, ?int $programId, ?string $reason, Request $request): SeasonPilgrim
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $seasonPilgrim, $programId, $reason, $request, $tenant): SeasonPilgrim {
            /** @var SeasonPilgrim $locked */
            $locked = SeasonPilgrim::query()->whereKey($seasonPilgrim->id)->lockForUpdate()->firstOrFail();
            /** @var Season $season */
            $season = $locked->season()->firstOrFail();
            SeasonGuard::assertWritable($season);
            SeasonGuard::assertNotTerminal($locked);

            if ($programId === null && $locked->status !== SeasonPilgrimStatus::Registered) {
                throw SeasonPilgrimDomainException::programRequired();
            }

            $fromId = $locked->season_program_id !== null ? (int) $locked->season_program_id : null;
            if ($programId === $fromId) {
                throw SeasonPilgrimDomainException::programUnchanged();
            }

            $program = $programId !== null ? $this->references->assignableProgram($season, $programId) : null;

            $locked->update(['season_program_id' => $program?->id]);

            $change = SeasonPilgrimProgramChange::query()->create([
                'season_id' => $locked->season_id,
                'season_pilgrim_id' => $locked->id,
                'from_season_program_id' => $fromId,
                'to_season_program_id' => $program?->id,
                'reason' => $reason,
                'changed_by' => $actor->id,
                'correlation_id' => $this->correlationId->get(),
            ]);

            $this->security->record(AuthorizationSecurityEvent::SEASON_PILGRIM_PROGRAM_CHANGED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_pilgrim_id' => $locked->id,
                'reference_number' => $locked->reference_number,
                'reason' => $reason,
                'before_values' => ['season_program_id' => $fromId],
                'after_values' => ['season_program_id' => $program?->id],
                'metadata' => ['season_id' => $locked->season_id, 'program_change_id' => $change->id],
            ], $request);

            return SeasonPilgrimLoader::load($locked);
        });
    }
}
