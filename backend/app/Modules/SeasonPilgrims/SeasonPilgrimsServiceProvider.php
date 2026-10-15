<?php

namespace App\Modules\SeasonPilgrims;

use App\Modules\SeasonPilgrims\Activation\IdentitySnapshotPresentRequirement;
use App\Modules\SeasonPilgrims\Activation\PilgrimGroupAssignedRequirement;
use App\Modules\SeasonPilgrims\Activation\SeasonPilgrimActivationValidator;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use App\Modules\SeasonPilgrims\Readiness\ParticipationClosingReadiness;
use App\Modules\SeasonPilgrims\Readiness\SeasonClosingReadiness;
use Illuminate\Support\ServiceProvider;

/**
 * Container wiring for the Season & Pilgrim foundation: the scoped Season
 * Context and the tagged extension points (closing readiness contributors,
 * activation requirements, operational reference checkers).
 */
class SeasonPilgrimsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(SeasonContext::class);

        $this->app->tag([ParticipationClosingReadiness::class], SeasonClosingReadiness::CONTRIBUTOR_TAG);

        $this->app->tag([
            PilgrimGroupAssignedRequirement::class,
            IdentitySnapshotPresentRequirement::class,
        ], SeasonPilgrimActivationValidator::REQUIREMENT_TAG);
    }
}
