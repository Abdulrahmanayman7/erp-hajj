<?php

namespace App\Modules\SeasonalTemplates\Adapters;

use App\Models\User;
use App\Modules\SeasonalTemplates\Support\SafeCopyAllowlist;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonProgram;

/**
 * season_programs: code, name, description, is_active. No pilgrim counts or links.
 */
final class SeasonProgramsSafeCopyAdapter extends AbstractSafeCopyAdapter
{
    public function entityKind(): string
    {
        return SafeCopyAllowlist::SEASON_PROGRAMS;
    }

    protected function sourceCandidates(Season $source): array
    {
        $rows = [];
        foreach (SeasonProgram::query()->where('season_id', $source->id)->orderBy('code')->get() as $program) {
            $rows[$program->code] = ['source_id' => $program->id, 'fields' => $this->fieldsOf($program)];
        }

        return $rows;
    }

    protected function targetRows(Season $target): array
    {
        $rows = [];
        foreach (SeasonProgram::query()->where('season_id', $target->id)->orderBy('code')->get() as $program) {
            $rows[$program->code] = ['id' => $program->id, 'fields' => $this->fieldsOf($program)];
        }

        return $rows;
    }

    protected function insertTarget(Season $target, array $fields, User $actor): ?int
    {
        $program = new SeasonProgram([
            'season_id' => $target->id,
            'code' => $fields['code'],
            'name' => $fields['name'],
            'description' => $fields['description'],
            'is_active' => $fields['is_active'],
            'created_by' => $actor->id,
        ]);
        $program->save();

        return $program->id;
    }

    protected function updateTarget(int $targetId, array $fields): void
    {
        SeasonProgram::query()->findOrFail($targetId)->update([
            'name' => $fields['name'],
            'description' => $fields['description'],
            'is_active' => $fields['is_active'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldsOf(SeasonProgram $program): array
    {
        return $this->allowlisted([
            'code' => $program->code,
            'name' => $program->name,
            'description' => $program->description,
            'is_active' => (bool) $program->is_active,
        ]);
    }
}
