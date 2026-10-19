<?php

namespace App\Modules\Accommodation\Policies;

use App\Models\User;
use App\Modules\Accommodation\Models\AccommodationAssignment;
use App\Modules\Accommodation\Models\AccommodationSeasonUnit;
use App\Modules\Accommodation\Support\AccommodationAccess;

class AccommodationAssignmentPolicy
{
    use AuthorizesAccommodation;

    public function viewAny(User $actor): bool
    {
        return $this->canRead($actor);
    }

    public function view(User $actor, AccommodationAssignment $assignment): bool
    {
        $unit = AccommodationSeasonUnit::query()->find($assignment->season_unit_id);

        return $unit !== null && $this->seesFacility($actor, (int) $unit->facility_id);
    }

    public function create(User $actor): bool
    {
        return $this->verb($actor, 'accommodation.draft');
    }

    public function update(User $actor, AccommodationAssignment $assignment): bool
    {
        return $this->verb($actor, 'accommodation.draft');
    }

    public function delete(User $actor, AccommodationAssignment $assignment): bool
    {
        return $this->verb($actor, 'accommodation.draft');
    }

    public function confirm(User $actor, AccommodationAssignment $assignment): bool
    {
        if (! $this->verb($actor, 'accommodation.confirm')) {
            return false;
        }

        $unit = AccommodationSeasonUnit::query()->find($assignment->season_unit_id);

        return $unit !== null && app(AccommodationAccess::class)->canSeeFacility($actor, (int) $unit->facility_id);
    }

    public function confirmFamily(User $actor): bool
    {
        return $this->verb($actor, 'accommodation.confirm');
    }

    public function arrive(User $actor, AccommodationAssignment $assignment): bool
    {
        return $this->opsVerb($actor, $assignment, 'accommodation.arrive');
    }

    public function checkIn(User $actor, AccommodationAssignment $assignment): bool
    {
        return $this->opsVerb($actor, $assignment, 'accommodation.check_in');
    }

    public function checkOut(User $actor, AccommodationAssignment $assignment): bool
    {
        return $this->opsVerb($actor, $assignment, 'accommodation.check_out');
    }

    public function cancelAssignment(User $actor, AccommodationAssignment $assignment): bool
    {
        return $this->opsVerb($actor, $assignment, 'accommodation.cancel_assignment');
    }

    public function relocate(User $actor, AccommodationAssignment $assignment): bool
    {
        return $this->opsVerb($actor, $assignment, 'accommodation.relocate');
    }

    private function opsVerb(User $actor, AccommodationAssignment $assignment, string $verb): bool
    {
        if (! $this->verb($actor, $verb)) {
            return false;
        }

        $unit = AccommodationSeasonUnit::query()->find($assignment->season_unit_id);

        return $unit !== null && app(AccommodationAccess::class)->canSeeFacility($actor, (int) $unit->facility_id);
    }
}
