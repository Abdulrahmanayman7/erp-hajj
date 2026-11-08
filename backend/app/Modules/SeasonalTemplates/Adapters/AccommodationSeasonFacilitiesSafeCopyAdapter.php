<?php

namespace App\Modules\SeasonalTemplates\Adapters;

use App\Models\User;
use App\Modules\Accommodation\Models\AccommodationFacility;
use App\Modules\Accommodation\Models\AccommodationSeasonFacility;
use App\Modules\SeasonalTemplates\Support\SafeCopyAllowlist;
use App\Modules\SeasonPilgrims\Models\Season;

/**
 * accommodation_season_facilities: facility (mapped by code), audience, is_active.
 *
 * The shared facility master is resolved by `code` and is NEVER created (a
 * missing master is a preview conflict). Units, positions, assignments, scopes
 * and locks are never copied.
 */
final class AccommodationSeasonFacilitiesSafeCopyAdapter extends AbstractSafeCopyAdapter
{
    public function entityKind(): string
    {
        return SafeCopyAllowlist::ACCOMMODATION_SEASON_FACILITIES;
    }

    protected function sourceCandidates(Season $source): array
    {
        return $this->rowsFor($source, 'source_id');
    }

    protected function targetRows(Season $target): array
    {
        return $this->rowsFor($target, 'id');
    }

    protected function dependencyConflict(Season $target, string $key, array $fields, array $selection): ?array
    {
        if ($this->targetFacilityId($key) !== null) {
            return null;
        }

        return ['dependency' => 'accommodation_facilities', 'facility_code' => $key];
    }

    protected function insertTarget(Season $target, array $fields, User $actor): ?int
    {
        $facilityId = $this->targetFacilityId((string) $fields['facility_code']);
        if ($facilityId === null) {
            return null;
        }

        $seasonFacility = new AccommodationSeasonFacility([
            'season_id' => $target->id,
            'facility_id' => $facilityId,
            'audience' => $fields['audience'],
            'is_active' => $fields['is_active'],
            'created_by' => $actor->id,
        ]);
        $seasonFacility->save();

        return $seasonFacility->id;
    }

    protected function updateTarget(int $targetId, array $fields): void
    {
        AccommodationSeasonFacility::query()->findOrFail($targetId)->update([
            'audience' => $fields['audience'],
            'is_active' => $fields['is_active'],
        ]);
    }

    private function targetFacilityId(string $code): ?int
    {
        $id = AccommodationFacility::query()->where('code', $code)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function rowsFor(Season $season, string $idKey): array
    {
        $rows = [];
        $seasonFacilities = AccommodationSeasonFacility::query()
            ->where('season_id', $season->id)
            ->with('facility')
            ->get()
            ->filter(static fn (AccommodationSeasonFacility $f): bool => $f->facility !== null)
            ->sortBy(static fn (AccommodationSeasonFacility $f): string => $f->facility->code);

        foreach ($seasonFacilities as $seasonFacility) {
            $rows[$seasonFacility->facility->code] = [
                $idKey => $seasonFacility->id,
                'fields' => $this->allowlisted([
                    'facility_code' => $seasonFacility->facility->code,
                    'audience' => $seasonFacility->audience,
                    'is_active' => (bool) $seasonFacility->is_active,
                ]),
            ];
        }

        return $rows;
    }
}
