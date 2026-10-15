<?php

namespace App\Modules\Transportation\Integration;

use App\Modules\SeasonPilgrims\Contracts\SeasonResourceDeletionChecker;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonFamilyGroup;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroup;
use App\Modules\SeasonPilgrims\Models\SeasonProgram;
use App\Modules\Transportation\Models\TransportEntitlement;
use App\Modules\Transportation\Models\TransportMovement;
use App\Modules\Transportation\Models\TransportRunPassenger;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use Illuminate\Support\Facades\DB;

/**
 * ADR-0018 I13: Transportation data keeps a Season, and an entitlement or
 * assignment snapshot keeps a Program, from hard delete. Group / family
 * source metadata never blocks.
 */
final class TransportationDeletionChecker implements SeasonResourceDeletionChecker
{
    public function blocksDeletion(Season|SeasonProgram|SeasonPilgrimGroup|SeasonFamilyGroup $resource): bool
    {
        return match (true) {
            $resource instanceof Season => $this->seasonReferenced($resource),
            $resource instanceof SeasonProgram => $this->programReferenced($resource),
            default => false,
        };
    }

    private function seasonReferenced(Season $season): bool
    {
        return TransportSeasonVehicle::query()->where('season_id', $season->id)->exists()
            || TransportEntitlement::query()->where('season_id', $season->id)->exists()
            || TransportMovement::query()->where('season_id', $season->id)->exists()
            || DB::table('transport_movement_sequences')
                ->where('tenant_id', $season->tenant_id)
                ->where('season_id', $season->id)
                ->exists();
    }

    private function programReferenced(SeasonProgram $program): bool
    {
        return TransportEntitlement::query()->where('season_program_id', $program->id)->exists()
            || TransportRunPassenger::query()
                ->where('season_id', $program->season_id)
                ->where('program_id_at_assignment', $program->id)
                ->exists();
    }
}
