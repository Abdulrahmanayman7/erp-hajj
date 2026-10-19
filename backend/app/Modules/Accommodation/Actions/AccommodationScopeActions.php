<?php

namespace App\Modules\Accommodation\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Accommodation\Exceptions\AccommodationDomainException;
use App\Modules\Accommodation\Models\AccommodationFacility;
use App\Modules\Accommodation\Models\AccommodationFacilityScope;
use App\Modules\Accommodation\Models\AccommodationSupervisorScope;
use App\Modules\Accommodation\Support\AccommodationAudit;
use App\Modules\Accommodation\Support\AccommodationPersistence;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AccommodationScopeActions
{
    public function __construct(
        private readonly AccommodationAudit $audit,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function grantFacility(User $actor, array $data, Request $request): AccommodationFacilityScope
    {
        return DB::transaction(function () use ($actor, $data, $request): AccommodationFacilityScope {
            $season = app(SeasonContext::class)->require();
            SeasonGuard::assertWritable($season);
            $grantee = $this->grantee((int) $data['user_id']);
            $facility = AccommodationFacility::query()->findOrFail($data['facility_id']);

            $row = AccommodationPersistence::save(function () use ($season, $facility, $grantee, $actor) {
                try {
                    return AccommodationFacilityScope::query()->create([
                        'season_id' => $season->id,
                        'facility_id' => $facility->id,
                        'user_id' => $grantee->id,
                        'granted_by' => $actor->id,
                    ]);
                } catch (QueryException $exception) {
                    if ($exception instanceof UniqueConstraintViolationException
                        || str_contains($exception->getMessage(), '1062')
                        || str_contains($exception->getMessage(), 'UNIQUE constraint failed')) {
                        throw AccommodationDomainException::scopeExists();
                    }

                    throw $exception;
                }
            });

            $this->audit->record(AuthorizationSecurityEvent::ACCOMMODATION_FACILITY_SCOPE_GRANTED, $actor->id, 'accommodation_facility_scope', $row->id, null, null, [
                'after_values' => ['user_id' => $grantee->id, 'facility_id' => $facility->id, 'season_id' => $season->id],
            ], $request);

            return $row;
        });
    }

    public function revokeFacility(User $actor, AccommodationFacilityScope $scope, Request $request): AccommodationFacilityScope
    {
        return DB::transaction(function () use ($actor, $scope, $request): AccommodationFacilityScope {
            SeasonGuard::assertWritable(app(SeasonContext::class)->require());
            $locked = AccommodationFacilityScope::query()->whereKey($scope->id)->lockForUpdate()->firstOrFail();

            if ($locked->revoked_at !== null) {
                throw AccommodationDomainException::alreadyRevoked();
            }

            $locked->revoked_at = now();
            $locked->save();
            $this->audit->record(AuthorizationSecurityEvent::ACCOMMODATION_FACILITY_SCOPE_REVOKED, $actor->id, 'accommodation_facility_scope', $locked->id, null, null, [
                'after_values' => ['revoked_at' => $locked->revoked_at?->utc()->format(DATE_ATOM)],
            ], $request);

            return $locked;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function grantSupervisor(User $actor, array $data, Request $request): AccommodationSupervisorScope
    {
        return DB::transaction(function () use ($actor, $data, $request): AccommodationSupervisorScope {
            $season = app(SeasonContext::class)->require();
            SeasonGuard::assertWritable($season);
            $grantee = $this->grantee((int) $data['user_id']);

            $row = AccommodationPersistence::save(function () use ($season, $grantee, $actor) {
                try {
                    return AccommodationSupervisorScope::query()->create([
                        'season_id' => $season->id,
                        'user_id' => $grantee->id,
                        'granted_by' => $actor->id,
                    ]);
                } catch (QueryException $exception) {
                    if ($exception instanceof UniqueConstraintViolationException
                        || str_contains($exception->getMessage(), '1062')
                        || str_contains($exception->getMessage(), 'UNIQUE constraint failed')) {
                        throw AccommodationDomainException::scopeExists();
                    }

                    throw $exception;
                }
            });

            $this->audit->record(AuthorizationSecurityEvent::ACCOMMODATION_SUPERVISOR_SCOPE_GRANTED, $actor->id, 'accommodation_supervisor_scope', $row->id, null, null, [
                'after_values' => ['user_id' => $grantee->id, 'season_id' => $season->id],
            ], $request);

            return $row;
        });
    }

    public function revokeSupervisor(User $actor, AccommodationSupervisorScope $scope, Request $request): AccommodationSupervisorScope
    {
        return DB::transaction(function () use ($actor, $scope, $request): AccommodationSupervisorScope {
            SeasonGuard::assertWritable(app(SeasonContext::class)->require());
            $locked = AccommodationSupervisorScope::query()->whereKey($scope->id)->lockForUpdate()->firstOrFail();

            if ($locked->revoked_at !== null) {
                throw AccommodationDomainException::alreadyRevoked();
            }

            $locked->revoked_at = now();
            $locked->save();
            $this->audit->record(AuthorizationSecurityEvent::ACCOMMODATION_SUPERVISOR_SCOPE_REVOKED, $actor->id, 'accommodation_supervisor_scope', $locked->id, null, null, [
                'after_values' => ['revoked_at' => $locked->revoked_at?->utc()->format(DATE_ATOM)],
            ], $request);

            return $locked;
        });
    }

    private function grantee(int $userId): User
    {
        $tenantId = $this->tenants->require()->id;
        $user = User::query()->whereKey($userId)->first();

        if ($user === null || $user->tenant_id === null || (int) $user->tenant_id !== (int) $tenantId) {
            throw AccommodationDomainException::granteeTenant();
        }

        return $user;
    }
}
