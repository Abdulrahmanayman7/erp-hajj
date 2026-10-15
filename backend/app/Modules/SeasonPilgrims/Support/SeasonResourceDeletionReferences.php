<?php

namespace App\Modules\SeasonPilgrims\Support;

use App\Modules\SeasonPilgrims\Contracts\SeasonResourceDeletionChecker;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonFamilyGroup;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroup;
use App\Modules\SeasonPilgrims\Models\SeasonProgram;
use Illuminate\Contracts\Container\Container;

final class SeasonResourceDeletionReferences
{
    public const CHECKER_TAG = 'season_pilgrims.deletion_reference_checkers';

    public function __construct(private readonly Container $container) {}

    public function assertDeletable(Season|SeasonProgram|SeasonPilgrimGroup|SeasonFamilyGroup $resource): void
    {
        /** @var SeasonResourceDeletionChecker $checker */
        foreach ($this->container->tagged(self::CHECKER_TAG) as $checker) {
            if ($checker->blocksDeletion($resource)) {
                throw SeasonPilgrimDomainException::resourceReferenced();
            }
        }
    }
}
