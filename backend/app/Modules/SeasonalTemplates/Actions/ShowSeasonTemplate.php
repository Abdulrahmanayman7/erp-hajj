<?php

namespace App\Modules\SeasonalTemplates\Actions;

use App\Modules\SeasonalTemplates\Models\SeasonTemplate;

final class ShowSeasonTemplate
{
    public function execute(SeasonTemplate $template): SeasonTemplate
    {
        return $template->load(['items', 'createdBy', 'approvedBy']);
    }
}
