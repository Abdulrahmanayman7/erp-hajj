<?php

namespace App\Modules\SeasonProjects\Enums;

/**
 * Informational phase labels only (E-Q03 = A — no automatic phase/task completion).
 */
enum SeasonProjectPhaseStatus: string
{
    case Planned = 'planned';
    case Active = 'active';
    case Done = 'done';
}
