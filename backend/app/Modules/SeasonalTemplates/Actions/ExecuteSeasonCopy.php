<?php

namespace App\Modules\SeasonalTemplates\Actions;

use App\Models\User;
use App\Modules\SeasonalTemplates\Models\SeasonCopyRun;
use App\Modules\SeasonalTemplates\Support\SafeCopyOrchestrator;
use Illuminate\Http\Request;

final class ExecuteSeasonCopy
{
    public function __construct(
        private readonly SafeCopyOrchestrator $orchestrator,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data, Request $request): SeasonCopyRun
    {
        return $this->orchestrator->executePreview($actor, $data, $request);
    }
}
