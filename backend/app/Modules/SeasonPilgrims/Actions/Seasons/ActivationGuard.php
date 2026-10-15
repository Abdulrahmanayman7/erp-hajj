<?php

namespace App\Modules\SeasonPilgrims\Actions\Seasons;

use App\Modules\SeasonPilgrims\Enums\SeasonStatus;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\Season;

final class ActivationGuard
{
    public static function assertNoOtherActive(Season $season): void
    {
        $otherActive = Season::query()
            ->where('status', SeasonStatus::Active->value)
            ->whereKeyNot($season->id)
            ->lockForUpdate()
            ->exists();

        if ($otherActive) {
            throw SeasonPilgrimDomainException::seasonActiveExists();
        }
    }
}
