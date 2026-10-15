<?php

namespace App\Modules\Transportation\Resources;

use App\Modules\Employees\Models\Employee;
use App\Modules\Transportation\Models\TransportExternalDriver;

/**
 * Minimal crew references for Transportation resources (no HR details).
 */
final class CrewSummary
{
    /**
     * @return array{id: int, employee_number: string|null, full_name: string}|null
     */
    public static function employee(?Employee $employee): ?array
    {
        return $employee === null ? null : [
            'id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'full_name' => $employee->full_name,
        ];
    }

    /**
     * @return array{id: int, full_name: string}|null
     */
    public static function externalDriver(?TransportExternalDriver $driver): ?array
    {
        return $driver === null ? null : [
            'id' => $driver->id,
            'full_name' => $driver->full_name,
        ];
    }
}
