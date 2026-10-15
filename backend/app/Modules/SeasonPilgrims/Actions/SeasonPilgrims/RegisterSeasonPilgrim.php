<?php

namespace App\Modules\SeasonPilgrims\Actions\SeasonPilgrims;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Shared\CorrelationId;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Enums\SeasonPilgrimStatus;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\Pilgrim;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimProgramChange;
use App\Modules\SeasonPilgrims\Support\IdentitySnapshotCapturer;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\SeasonPilgrims\Support\SeasonPilgrimReferenceNumberGenerator;
use App\Modules\SeasonPilgrims\Support\SeasonReferenceResolver;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Registers a pilgrim (possibly provisional) in the context season with a
 * new seasonal reference number and an initial identity snapshot.
 */
final class RegisterSeasonPilgrim
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SeasonPilgrimReferenceNumberGenerator $numbers,
        private readonly SeasonReferenceResolver $references,
        private readonly IdentitySnapshotCapturer $snapshots,
        private readonly CorrelationId $correlationId,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Season $season, array $data, Request $request): SeasonPilgrim
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $season, $data, $request, $tenant): SeasonPilgrim {
            /** @var Season $season */
            $season = Season::query()->whereKey($season->id)->lockForUpdate()->firstOrFail();
            SeasonGuard::assertWritable($season);

            $pilgrim = Pilgrim::query()->whereKey((int) $data['pilgrim_id'])->firstOrFail();

            if (SeasonPilgrim::query()->where('season_id', $season->id)->where('pilgrim_id', $pilgrim->id)->exists()) {
                throw SeasonPilgrimDomainException::seasonPilgrimAlreadyRegistered();
            }

            $program = isset($data['season_program_id'])
                ? $this->references->assignableProgram($season, (int) $data['season_program_id'])
                : null;
            $group = isset($data['season_pilgrim_group_id'])
                ? $this->references->pilgrimGroup($season, (int) $data['season_pilgrim_group_id'])
                : null;

            try {
                $seasonPilgrim = SeasonPilgrim::query()->create([
                    'season_id' => $season->id,
                    'pilgrim_id' => $pilgrim->id,
                    'reference_number' => $this->numbers->next($season),
                    'status' => SeasonPilgrimStatus::Registered,
                    'season_program_id' => $program?->id,
                    'season_pilgrim_group_id' => $group?->id,
                    'registered_at' => now(),
                    'registered_by' => $actor->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw SeasonPilgrimDomainException::seasonPilgrimAlreadyRegistered();
            }

            $documentId = isset($data['pilgrim_identity_document_id']) ? (int) $data['pilgrim_identity_document_id'] : null;
            $this->snapshots->capture($seasonPilgrim, $documentId, false, $actor->id);

            if ($program !== null) {
                SeasonPilgrimProgramChange::query()->create([
                    'season_id' => $seasonPilgrim->season_id,
                    'season_pilgrim_id' => $seasonPilgrim->id,
                    'from_season_program_id' => null,
                    'to_season_program_id' => $program->id,
                    'reason' => null,
                    'changed_by' => $actor->id,
                    'correlation_id' => $this->correlationId->get(),
                ]);
            }

            $this->security->record(AuthorizationSecurityEvent::SEASON_PILGRIM_REGISTERED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_pilgrim_id' => $seasonPilgrim->id,
                'reference_number' => $seasonPilgrim->reference_number,
                'pilgrim_name' => $pilgrim->full_name,
                'to_status' => SeasonPilgrimStatus::Registered->value,
                'metadata' => [
                    'season_id' => $season->id,
                    'pilgrim_id' => $pilgrim->id,
                    'season_program_id' => $program?->id,
                    'season_pilgrim_group_id' => $group?->id,
                ],
            ], $request);

            return SeasonPilgrimLoader::load($seasonPilgrim);
        });
    }
}
