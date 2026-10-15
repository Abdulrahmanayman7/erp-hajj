<?php

namespace App\Modules\Transportation\Support;

use App\Modules\Transportation\Models\TransportMovement;
use App\Modules\Transportation\Models\TransportVehicleRun;

/**
 * Deterministic lock order for Run work: movement → run (then resources,
 * then SeasonPilgrims, then passengers in callers).
 */
final class RunLocks
{
    /**
     * @return array{0: TransportMovement, 1: TransportVehicleRun}
     */
    public static function lock(int $runId): array
    {
        $movementId = (int) TransportVehicleRun::query()->whereKey($runId)->value('transport_movement_id');

        /** @var TransportMovement $movement */
        $movement = TransportMovement::query()->whereKey($movementId)->lockForUpdate()->firstOrFail();

        /** @var TransportVehicleRun $run */
        $run = TransportVehicleRun::query()->whereKey($runId)->lockForUpdate()->firstOrFail();

        return [$movement, $run];
    }
}
