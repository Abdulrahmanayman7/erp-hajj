<?php

namespace App\Modules\SeasonalTemplates\Adapters;

use App\Models\User;
use App\Modules\SeasonalTemplates\Support\SafeCopyAllowlist;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroup;

/**
 * season_pilgrim_groups: EMPTY groups only (code, name, notes). Never members
 * (season_pilgrims.group_id) and never supervisors.
 */
final class SeasonPilgrimGroupsSafeCopyAdapter extends AbstractSafeCopyAdapter
{
    public function entityKind(): string
    {
        return SafeCopyAllowlist::SEASON_PILGRIM_GROUPS;
    }

    protected function sourceCandidates(Season $source): array
    {
        $rows = [];
        foreach (SeasonPilgrimGroup::query()->where('season_id', $source->id)->orderBy('code')->get() as $group) {
            $rows[$group->code] = ['source_id' => $group->id, 'fields' => $this->fieldsOf($group)];
        }

        return $rows;
    }

    protected function targetRows(Season $target): array
    {
        $rows = [];
        foreach (SeasonPilgrimGroup::query()->where('season_id', $target->id)->orderBy('code')->get() as $group) {
            $rows[$group->code] = ['id' => $group->id, 'fields' => $this->fieldsOf($group)];
        }

        return $rows;
    }

    protected function insertTarget(Season $target, array $fields, User $actor): ?int
    {
        $group = new SeasonPilgrimGroup([
            'season_id' => $target->id,
            'code' => $fields['code'],
            'name' => $fields['name'],
            'notes' => $fields['notes'],
            'created_by' => $actor->id,
        ]);
        $group->save();

        return $group->id;
    }

    protected function updateTarget(int $targetId, array $fields): void
    {
        SeasonPilgrimGroup::query()->findOrFail($targetId)->update([
            'name' => $fields['name'],
            'notes' => $fields['notes'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldsOf(SeasonPilgrimGroup $group): array
    {
        return $this->allowlisted([
            'code' => $group->code,
            'name' => $group->name,
            'notes' => $group->notes,
        ]);
    }
}
