<?php

namespace App\Modules\Tasks\Enums;

/**
 * Persisted projection of calculated task delay (S06 / E-Q05 = A).
 * Calculated delay remains authoritative; this column is synchronized only.
 */
enum TaskDelayState: string
{
    case None = 'none';
    case Delayed = 'delayed';
}
