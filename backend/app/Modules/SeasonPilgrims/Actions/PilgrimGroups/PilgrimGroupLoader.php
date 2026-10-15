<?php

namespace App\Modules\SeasonPilgrims\Actions\PilgrimGroups;

use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroup;

final class PilgrimGroupLoader
{
    public static function load(SeasonPilgrimGroup $group): SeasonPilgrimGroup
    {
        return $group->load([
            'supervisors' => fn ($q) => $q->with('employee')->orderBy('assigned_at')->orderBy('id'),
        ])->loadCount('seasonPilgrims');
    }
}
