<?php

namespace App\Modules\SeasonPilgrims\Support;

use App\Modules\SeasonPilgrims\Contracts\SeasonPilgrimOperationalReferenceChecker;
use App\Modules\SeasonPilgrims\Enums\SeasonPilgrimStatus;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use Illuminate\Contracts\Container\Container;

/**
 * F7: a SeasonPilgrim is operationally referenced once it has ever been
 * activated (foundation rule) or when any tagged checker from another
 * domain reports a reference.
 */
final class SeasonPilgrimOperationalReferences
{
    public const CHECKER_TAG = 'season_pilgrims.operational_reference_checkers';

    public function __construct(private readonly Container $container) {}

    public function isReferenced(SeasonPilgrim $seasonPilgrim): bool
    {
        if ($seasonPilgrim->status !== SeasonPilgrimStatus::Registered || $seasonPilgrim->activated_at !== null) {
            return true;
        }

        /** @var SeasonPilgrimOperationalReferenceChecker $checker */
        foreach ($this->container->tagged(self::CHECKER_TAG) as $checker) {
            if ($checker->isReferenced($seasonPilgrim)) {
                return true;
            }
        }

        return false;
    }
}
