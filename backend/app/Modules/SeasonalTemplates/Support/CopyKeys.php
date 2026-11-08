<?php

namespace App\Modules\SeasonalTemplates\Support;

use App\Modules\SeasonProjects\Models\SeasonProject;

/**
 * Template-local copy keys (`code`) for kinds without a natural business key.
 * Duplicates get a deterministic `#n` suffix in encounter order (id order for live rows).
 */
final class CopyKeys
{
    public const MAX_LENGTH = 255;

    /**
     * @param  array<string, true>  $seen
     */
    public static function unique(string $base, array &$seen): string
    {
        $code = $base;
        $n = 2;
        while (isset($seen[$code])) {
            $code = $base.'#'.$n++;
        }
        $seen[$code] = true;

        return $code;
    }

    public static function phase(string $projectCode, int $sortOrder): string
    {
        return $projectCode.'::'.$sortOrder;
    }

    public static function task(string $projectCode, string $title): string
    {
        return $projectCode.'::'.$title;
    }

    /**
     * Projects of one season by copy key (title, `#n` on duplicates), id order.
     *
     * @return array<string, SeasonProject>
     */
    public static function projectsOfSeason(int $seasonId): array
    {
        $seen = [];
        $projects = [];

        foreach (SeasonProject::query()->where('season_id', $seasonId)->orderBy('id')->get() as $project) {
            $projects[self::unique(trim($project->title), $seen)] = $project;
        }

        return $projects;
    }

    /**
     * @param  array<string, SeasonProject>  $projects  from {@see projectsOfSeason()}
     * @return array<int, string> project id => copy key
     */
    public static function projectCodesById(array $projects): array
    {
        $codes = [];
        foreach ($projects as $code => $project) {
            $codes[$project->id] = (string) $code;
        }

        return $codes;
    }
}
