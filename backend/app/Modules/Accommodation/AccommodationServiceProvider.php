<?php

namespace App\Modules\Accommodation;

use App\Modules\Accommodation\Models\AccommodationAssignment;
use App\Modules\Accommodation\Models\AccommodationFacility;
use App\Modules\Accommodation\Models\AccommodationFacilityScope;
use App\Modules\Accommodation\Models\AccommodationPosition;
use App\Modules\Accommodation\Models\AccommodationSeasonFacility;
use App\Modules\Accommodation\Models\AccommodationSeasonPosition;
use App\Modules\Accommodation\Models\AccommodationSeasonUnit;
use App\Modules\Accommodation\Models\AccommodationStructure;
use App\Modules\Accommodation\Models\AccommodationSupervisorScope;
use App\Modules\Accommodation\Models\AccommodationUnit;
use App\Modules\Accommodation\Policies\AccommodationAssignmentPolicy;
use App\Modules\Accommodation\Policies\AccommodationFacilityPolicy;
use App\Modules\Accommodation\Policies\AccommodationFacilityScopePolicy;
use App\Modules\Accommodation\Policies\AccommodationPositionPolicy;
use App\Modules\Accommodation\Policies\AccommodationSeasonFacilityPolicy;
use App\Modules\Accommodation\Policies\AccommodationSeasonPositionPolicy;
use App\Modules\Accommodation\Policies\AccommodationSeasonUnitPolicy;
use App\Modules\Accommodation\Policies\AccommodationStructurePolicy;
use App\Modules\Accommodation\Policies\AccommodationSupervisorScopePolicy;
use App\Modules\Accommodation\Policies\AccommodationUnitPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AccommodationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(AccommodationFacility::class, AccommodationFacilityPolicy::class);
        Gate::policy(AccommodationStructure::class, AccommodationStructurePolicy::class);
        Gate::policy(AccommodationUnit::class, AccommodationUnitPolicy::class);
        Gate::policy(AccommodationPosition::class, AccommodationPositionPolicy::class);
        Gate::policy(AccommodationSeasonFacility::class, AccommodationSeasonFacilityPolicy::class);
        Gate::policy(AccommodationSeasonUnit::class, AccommodationSeasonUnitPolicy::class);
        Gate::policy(AccommodationSeasonPosition::class, AccommodationSeasonPositionPolicy::class);
        Gate::policy(AccommodationAssignment::class, AccommodationAssignmentPolicy::class);
        Gate::policy(AccommodationFacilityScope::class, AccommodationFacilityScopePolicy::class);
        Gate::policy(AccommodationSupervisorScope::class, AccommodationSupervisorScopePolicy::class);
    }
}
