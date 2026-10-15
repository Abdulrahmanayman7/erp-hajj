<?php

namespace App\Modules\SeasonPilgrims\Contracts;

use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;

/**
 * Extension point for F7: future operational domains report whether they
 * reference a SeasonPilgrim. Register implementations under the
 * `season_pilgrims.operational_reference_checkers` tag. Any positive
 * answer forbids hard delete (cancellation must be used instead).
 */
interface SeasonPilgrimOperationalReferenceChecker
{
    public function isReferenced(SeasonPilgrim $seasonPilgrim): bool;
}
