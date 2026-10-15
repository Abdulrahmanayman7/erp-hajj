<?php

namespace App\Modules\SeasonPilgrims\Support;

use App\Modules\SeasonPilgrims\Enums\SeasonPilgrimStatus;
use App\Modules\SeasonPilgrims\Enums\SeasonStatus;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;

/**
 * Lifecycle write guards shared by season-scoped actions. Selecting a
 * Closed season as context grants read access only (F16, F35).
 */
final class SeasonGuard
{
    public static function assertWritable(Season $season): void
    {
        if (! $season->isWritable()) {
            throw SeasonPilgrimDomainException::seasonClosed();
        }
    }

    public static function assertActive(Season $season): void
    {
        self::assertWritable($season);

        if ($season->status !== SeasonStatus::Active) {
            throw SeasonPilgrimDomainException::seasonNotActive();
        }
    }

    public static function assertNotTerminal(SeasonPilgrim $seasonPilgrim): void
    {
        if ($seasonPilgrim->status->isTerminal()) {
            throw SeasonPilgrimDomainException::seasonPilgrimTerminal();
        }
    }

    public static function assertRegistered(SeasonPilgrim $seasonPilgrim): void
    {
        if ($seasonPilgrim->status !== SeasonPilgrimStatus::Registered) {
            throw SeasonPilgrimDomainException::seasonPilgrimInvalidTransition();
        }
    }
}
