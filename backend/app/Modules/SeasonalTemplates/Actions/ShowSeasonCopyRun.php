<?php

namespace App\Modules\SeasonalTemplates\Actions;

use App\Modules\SeasonalTemplates\Models\SeasonCopyRun;

final class ShowSeasonCopyRun
{
    public function execute(SeasonCopyRun $run): SeasonCopyRun
    {
        return $run->load('lines');
    }
}
