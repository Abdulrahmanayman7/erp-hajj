<?php

namespace App\Modules\Accommodation\Support;

use App\Models\User;
use App\Modules\Accommodation\Exceptions\AccommodationDomainException;
use App\Modules\Accommodation\Models\AccommodationFacilityScope;
use App\Modules\Accommodation\Models\AccommodationSupervisorScope;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use Illuminate\Database\Eloquent\Builder;

final class AccommodationAccess
{
    public function __construct(private readonly SeasonContext $seasons) {}

    /**
     * Null means the actor supervises every facility in the context season.
     *
     * @return list<int>|null
     */
    public function visibleFacilityIds(User $actor): ?array
    {
        $seasonId = $this->seasons->require()->id;

        if ($this->supervisorQuery($actor, $seasonId)->exists()) {
            return null;
        }

        return $this->facilityQuery($actor, $seasonId)
            ->pluck('facility_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    public function canSeeFacility(User $actor, int $facilityId): bool
    {
        $ids = $this->visibleFacilityIds($actor);

        return $ids === null || in_array($facilityId, $ids, true);
    }

    public function lockAndAssert(User $actor, int $facilityId): void
    {
        $seasonId = $this->seasons->require()->id;

        if ($this->supervisorQuery($actor, $seasonId)->lockForUpdate()->exists()) {
            return;
        }

        $live = $this->facilityQuery($actor, $seasonId)
            ->where('facility_id', $facilityId)
            ->lockForUpdate()
            ->exists();

        if (! $live) {
            throw AccommodationDomainException::forbidden();
        }
    }

    private function supervisorQuery(User $actor, int $seasonId): Builder
    {
        return AccommodationSupervisorScope::query()
            ->where('season_id', $seasonId)
            ->where('user_id', $actor->id)
            ->whereNull('revoked_at');
    }

    private function facilityQuery(User $actor, int $seasonId): Builder
    {
        return AccommodationFacilityScope::query()
            ->where('season_id', $seasonId)
            ->where('user_id', $actor->id)
            ->whereNull('revoked_at');
    }
}
