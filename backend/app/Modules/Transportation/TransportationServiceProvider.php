<?php

namespace App\Modules\Transportation;

use App\Modules\SeasonPilgrims\Events\SeasonPilgrimCancelled;
use App\Modules\SeasonPilgrims\Readiness\SeasonClosingReadiness;
use App\Modules\SeasonPilgrims\Support\SeasonPilgrimOperationalReferences;
use App\Modules\SeasonPilgrims\Support\SeasonResourceDeletionReferences;
use App\Modules\Transportation\Integration\TransportationClosingReadiness;
use App\Modules\Transportation\Integration\TransportationDeletionChecker;
use App\Modules\Transportation\Integration\TransportationOperationalReferenceChecker;
use App\Modules\Transportation\Listeners\HandleSeasonPilgrimCancelled;
use App\Modules\Transportation\Models\TransportEntitlement;
use App\Modules\Transportation\Models\TransportExternalDriver;
use App\Modules\Transportation\Models\TransportMovement;
use App\Modules\Transportation\Models\TransportPoint;
use App\Modules\Transportation\Models\TransportReviewItem;
use App\Modules\Transportation\Models\TransportRunPassenger;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use App\Modules\Transportation\Models\TransportTimelineEvent;
use App\Modules\Transportation\Models\TransportVehicle;
use App\Modules\Transportation\Models\TransportVehicleRun;
use App\Modules\Transportation\Policies\TransportEntitlementPolicy;
use App\Modules\Transportation\Policies\TransportExternalDriverPolicy;
use App\Modules\Transportation\Policies\TransportMovementPolicy;
use App\Modules\Transportation\Policies\TransportPointPolicy;
use App\Modules\Transportation\Policies\TransportReviewItemPolicy;
use App\Modules\Transportation\Policies\TransportRunPassengerPolicy;
use App\Modules\Transportation\Policies\TransportSeasonVehiclePolicy;
use App\Modules\Transportation\Policies\TransportTimelineEventPolicy;
use App\Modules\Transportation\Policies\TransportVehiclePolicy;
use App\Modules\Transportation\Policies\TransportVehicleRunPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Transportation wiring: foundation extension points (closing readiness,
 * operational and deletion references), the SeasonPilgrimCancelled
 * listener, and policies. The foundation never imports this module.
 */
class TransportationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([TransportationClosingReadiness::class], SeasonClosingReadiness::CONTRIBUTOR_TAG);
        $this->app->tag([TransportationOperationalReferenceChecker::class], SeasonPilgrimOperationalReferences::CHECKER_TAG);
        $this->app->tag([TransportationDeletionChecker::class], SeasonResourceDeletionReferences::CHECKER_TAG);
    }

    public function boot(): void
    {
        Event::listen(SeasonPilgrimCancelled::class, HandleSeasonPilgrimCancelled::class);

        Gate::policy(TransportPoint::class, TransportPointPolicy::class);
        Gate::policy(TransportVehicle::class, TransportVehiclePolicy::class);
        Gate::policy(TransportExternalDriver::class, TransportExternalDriverPolicy::class);
        Gate::policy(TransportSeasonVehicle::class, TransportSeasonVehiclePolicy::class);
        Gate::policy(TransportEntitlement::class, TransportEntitlementPolicy::class);
        Gate::policy(TransportMovement::class, TransportMovementPolicy::class);
        Gate::policy(TransportVehicleRun::class, TransportVehicleRunPolicy::class);
        Gate::policy(TransportRunPassenger::class, TransportRunPassengerPolicy::class);
        Gate::policy(TransportTimelineEvent::class, TransportTimelineEventPolicy::class);
        Gate::policy(TransportReviewItem::class, TransportReviewItemPolicy::class);
    }
}
