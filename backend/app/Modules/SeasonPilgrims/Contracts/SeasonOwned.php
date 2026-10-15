<?php

namespace App\Modules\SeasonPilgrims\Contracts;

/**
 * Marker for season-scoped records (season_id column). Route-bound models
 * implementing this contract must belong to the resolved Season Context,
 * otherwise the request is answered with 404 (see ResolveSeasonContext).
 */
interface SeasonOwned
{
    public function owningSeasonId(): int;
}
