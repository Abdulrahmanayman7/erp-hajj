<?php

namespace App\Modules\Transportation\Support;

use App\Modules\Transportation\Enums\EntitlementStatus;
use App\Modules\Transportation\Enums\ServiceLevel;
use App\Modules\Transportation\Enums\VehicleClass;
use App\Modules\Transportation\Models\TransportEntitlement;
use App\Modules\Transportation\Models\TransportRunPassenger;
use App\Modules\Transportation\Models\TransportSeasonVehicle;

/**
 * ADR-0018 T9 / I10: compares a Program's entitlement with a vehicle's
 * class and service level. Never moves a passenger.
 */
final class EntitlementEvaluator
{
    /**
     * @var array<int, TransportEntitlement|null>
     */
    private array $cache = [];

    public function entitlementFor(?int $seasonProgramId): ?TransportEntitlement
    {
        if ($seasonProgramId === null) {
            return null;
        }

        if (! array_key_exists($seasonProgramId, $this->cache)) {
            $this->cache[$seasonProgramId] = TransportEntitlement::query()
                ->where('season_program_id', $seasonProgramId)
                ->first();
        }

        return $this->cache[$seasonProgramId];
    }

    public function evaluate(?int $seasonProgramId, ?TransportSeasonVehicle $seasonVehicle): EntitlementStatus
    {
        $entitlement = $this->entitlementFor($seasonProgramId);

        if ($entitlement === null) {
            return EntitlementStatus::None;
        }

        $class = $seasonVehicle?->vehicle?->vehicle_class;
        $level = $seasonVehicle?->service_level;

        return $entitlement->matches(
            $class instanceof VehicleClass ? $class : null,
            $level instanceof ServiceLevel ? $level : null,
        ) ? EntitlementStatus::Compatible : EntitlementStatus::Mismatch;
    }

    /**
     * Live status shown on the manifest: current Program vs. the Run's
     * current vehicle. A live override surfaces as `overridden` only while
     * the evaluation is still a mismatch; if Program / entitlement / vehicle
     * later become compatible, live status becomes `compatible` without
     * rewriting the immutable assignment context.
     */
    public function liveStatus(TransportRunPassenger $passenger, ?int $currentProgramId, ?TransportSeasonVehicle $seasonVehicle): EntitlementStatus
    {
        $evaluated = $this->evaluate($currentProgramId, $seasonVehicle);

        if ($passenger->entitlement_override && $evaluated === EntitlementStatus::Mismatch) {
            return EntitlementStatus::Overridden;
        }

        return $evaluated;
    }
}
