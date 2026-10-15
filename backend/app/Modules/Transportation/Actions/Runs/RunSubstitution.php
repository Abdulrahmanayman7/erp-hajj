<?php

namespace App\Modules\Transportation\Actions\Runs;

use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportVehicleRun;

/**
 * T7: resource changes are allowed on non-terminal Runs; once execution
 * has begun they need a reason.
 */
final class RunSubstitution
{
    public static function assertAllowed(TransportVehicleRun $run, ?string $reason): ?string
    {
        if ($run->status->isTerminal()) {
            throw TransportationDomainException::runNotEditable();
        }

        $reason = $reason !== null && trim($reason) !== '' ? trim($reason) : null;

        if ($run->status->hasStarted() && $reason === null) {
            throw TransportationDomainException::substitutionReasonRequired();
        }

        return $reason;
    }
}
