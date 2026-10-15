<?php

namespace App\Modules\SeasonPilgrims\Support;

use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonFamilyGroup;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroup;
use App\Modules\SeasonPilgrims\Models\SeasonProgram;

/**
 * Resolves ids sent in payloads strictly inside the given season (and,
 * through the tenant scope, the current tenant). Ids from another season
 * or tenant behave as nonexistent. The composite foreign keys on
 * season_pilgrims are the database backstop.
 */
final class SeasonReferenceResolver
{
    public function assignableProgram(Season $season, int $programId): SeasonProgram
    {
        $program = SeasonProgram::query()
            ->where('season_id', $season->id)
            ->whereKey($programId)
            ->first();

        if ($program === null) {
            throw SeasonPilgrimDomainException::programInvalid();
        }

        if (! $program->is_active) {
            throw SeasonPilgrimDomainException::programInactive();
        }

        return $program;
    }

    public function pilgrimGroup(Season $season, int $groupId): SeasonPilgrimGroup
    {
        return SeasonPilgrimGroup::query()
            ->where('season_id', $season->id)
            ->whereKey($groupId)
            ->first() ?? throw SeasonPilgrimDomainException::pilgrimGroupInvalid();
    }

    public function familyGroup(Season $season, int $groupId, bool $lock = false): SeasonFamilyGroup
    {
        return SeasonFamilyGroup::query()
            ->where('season_id', $season->id)
            ->whereKey($groupId)
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->first() ?? throw SeasonPilgrimDomainException::familyGroupInvalid();
    }
}
