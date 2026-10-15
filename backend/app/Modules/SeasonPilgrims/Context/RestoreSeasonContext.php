<?php

namespace App\Modules\SeasonPilgrims\Context;

use App\Modules\SeasonPilgrims\Models\Season;
use Closure;
use RuntimeException;

/**
 * Job middleware (after RestoreTenantContext): re-loads the captured season
 * through the tenant-scoped query and runs the job inside runInSeason().
 * A season that no longer exists in the restored tenant fails the job.
 */
class RestoreSeasonContext
{
    public function handle(object $job, Closure $next): void
    {
        $season = $job->seasonId === null
            ? null
            : Season::query()->whereKey($job->seasonId)->first();

        if ($season === null) {
            $job->fail(new RuntimeException(
                "Queued job references a missing season [{$job->seasonId}].",
            ));

            return;
        }

        app(SeasonContext::class)->runInSeason($season, fn () => $next($job));
    }
}
