<?php

namespace App\Modules\SeasonPilgrims\Context;

use App\Core\Tenancy\TenantContext;
use App\Modules\SeasonPilgrims\Enums\SeasonStatus;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Resolves the requested season strictly through the tenant-scoped Season
 * query: a season id from another tenant is indistinguishable from a
 * nonexistent one (404). No requested id → the tenant's Active season.
 */
final class SeasonContextResolver
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * @return array{0: Season, 1: bool} [season, explicit]
     */
    public function resolve(mixed $requestedSeasonId): array
    {
        $this->tenantContext->require();

        if ($requestedSeasonId === null || $requestedSeasonId === '') {
            $active = Season::query()->where('status', SeasonStatus::Active->value)->first();

            if ($active === null) {
                throw SeasonPilgrimDomainException::seasonContextMissing();
            }

            return [$active, false];
        }

        if (! is_string($requestedSeasonId) || ! ctype_digit($requestedSeasonId) || (int) $requestedSeasonId < 1) {
            throw SeasonPilgrimDomainException::seasonContextInvalid();
        }

        $season = Season::query()->whereKey((int) $requestedSeasonId)->first();

        if ($season === null) {
            throw (new ModelNotFoundException)->setModel(Season::class, [(int) $requestedSeasonId]);
        }

        return [$season, true];
    }
}
