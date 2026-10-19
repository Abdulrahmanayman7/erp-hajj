<?php

namespace App\Modules\Accommodation\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Accommodation\Models\AccommodationFacility;
use App\Modules\Accommodation\Models\AccommodationSeasonFacility;
use App\Modules\Accommodation\Support\AccommodationAudit;
use App\Modules\Accommodation\Support\AccommodationPersistence;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AccommodationSeasonFacilityActions
{
    public function __construct(private readonly AccommodationAudit $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data, Request $request): AccommodationSeasonFacility
    {
        return DB::transaction(function () use ($actor, $data, $request): AccommodationSeasonFacility {
            $season = app(SeasonContext::class)->require();
            SeasonGuard::assertWritable($season);
            $facility = AccommodationFacility::query()->findOrFail($data['facility_id']);

            $row = AccommodationPersistence::save(fn () => AccommodationSeasonFacility::query()->create([
                'season_id' => $season->id,
                'facility_id' => $facility->id,
                'audience' => $data['audience'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $actor->id,
            ]));

            $this->audit->record(AuthorizationSecurityEvent::ACCOMMODATION_SEASON_FACILITY_CREATED, $actor->id, 'accommodation_season_facility', $row->id, $facility->code, $facility->name, [
                'after_values' => ['facility_id' => $facility->id, 'audience' => $row->audience, 'capacity_reserved' => false],
            ], $request);

            return $row;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, AccommodationSeasonFacility $row, array $data, Request $request): AccommodationSeasonFacility
    {
        return DB::transaction(function () use ($actor, $row, $data, $request): AccommodationSeasonFacility {
            SeasonGuard::assertWritable(app(SeasonContext::class)->require());
            $locked = AccommodationSeasonFacility::query()->whereKey($row->id)->lockForUpdate()->firstOrFail();
            $locked->fill([
                'audience' => array_key_exists('audience', $data) ? $data['audience'] : $locked->audience,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $locked->is_active,
            ]);
            AccommodationPersistence::save(fn () => $locked->save());
            $this->audit->record(AuthorizationSecurityEvent::ACCOMMODATION_SEASON_FACILITY_UPDATED, $actor->id, 'accommodation_season_facility', $locked->id, null, null, [
                'after_values' => ['audience' => $locked->audience, 'is_active' => $locked->is_active],
            ], $request);

            return $locked;
        });
    }

    public function delete(User $actor, AccommodationSeasonFacility $row, Request $request): void
    {
        DB::transaction(function () use ($actor, $row, $request): void {
            SeasonGuard::assertWritable(app(SeasonContext::class)->require());
            $locked = AccommodationSeasonFacility::query()->whereKey($row->id)->lockForUpdate()->firstOrFail();
            $this->audit->record(AuthorizationSecurityEvent::ACCOMMODATION_SEASON_FACILITY_DELETED, $actor->id, 'accommodation_season_facility', $locked->id, null, null, [], $request);
            AccommodationPersistence::save(fn () => $locked->delete());
        });
    }
}
