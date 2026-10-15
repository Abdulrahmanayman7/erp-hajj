<?php

namespace App\Modules\SeasonPilgrims\Contracts;

use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Readiness\ReadinessResult;

/**
 * Season Closing Readiness contributor (F34). Future domains register an
 * implementation under the `season_pilgrims.closing_readiness` container
 * tag; Season never references their internals. Any BLOCKING result
 * prevents Active → Closed; WARNING results are reported only.
 */
interface SeasonClosingReadinessContributor
{
    /**
     * @return list<ReadinessResult>
     */
    public function evaluate(Season $season): array;
}
