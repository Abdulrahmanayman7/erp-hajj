<?php

namespace App\Modules\SeasonalTemplates\Actions;

use App\Models\User;
use App\Modules\SeasonalTemplates\Models\SeasonCopyPreview;
use App\Modules\SeasonalTemplates\Support\SafeCopyOrchestrator;
use Illuminate\Http\Request;

final class CreateSeasonCopyPreview
{
    public function __construct(
        private readonly SafeCopyOrchestrator $orchestrator,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data, Request $request): SeasonCopyPreview
    {
        return $this->orchestrator->createPreview($actor, $data, $request);
    }
}
