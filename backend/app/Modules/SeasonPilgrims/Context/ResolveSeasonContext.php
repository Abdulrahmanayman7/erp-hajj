<?php

namespace App\Modules\SeasonPilgrims\Context;

use App\Modules\SeasonPilgrims\Contracts\SeasonOwned;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware for season-scoped endpoints (alias `season.context`).
 *
 * Runs after tenant resolution and route-model binding:
 * 1. resolves the season from the X-Season-Id header (tenant-scoped) or
 *    defaults to the Active season;
 * 2. rejects (404) any bound SeasonOwned model that belongs to a different
 *    season than the resolved context;
 * 3. clears the context in `finally`.
 */
final class ResolveSeasonContext
{
    public function __construct(
        private readonly SeasonContext $context,
        private readonly SeasonContextResolver $resolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $header = (string) config('season_pilgrims.season_context_header', 'X-Season-Id');
            [$season, $explicit] = $this->resolver->resolve($request->header($header));

            $this->context->set($season, $explicit);

            foreach ($request->route()?->parameters() ?? [] as $parameter) {
                if ($parameter instanceof SeasonOwned && $parameter->owningSeasonId() !== (int) $season->id) {
                    throw (new ModelNotFoundException)->setModel($parameter::class);
                }
            }

            return $next($request);
        } finally {
            $this->context->clear();
        }
    }
}
