<?php

namespace App\Modules\SeasonalTemplates\Actions;

use App\Modules\SeasonalTemplates\Models\SeasonCopyPreview;

final class ShowSeasonCopyPreview
{
    public function execute(SeasonCopyPreview $preview): SeasonCopyPreview
    {
        return $preview->load('lines');
    }
}
