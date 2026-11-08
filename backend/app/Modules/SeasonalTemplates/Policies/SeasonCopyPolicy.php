<?php

namespace App\Modules\SeasonalTemplates\Policies;

use App\Models\User;
use App\Modules\SeasonalTemplates\Models\SeasonCopyPreview;
use App\Modules\SeasonalTemplates\Models\SeasonCopyRun;
use Illuminate\Database\Eloquent\Model;

/**
 * Registered for both {@see SeasonCopyPreview} and {@see SeasonCopyRun}.
 */
class SeasonCopyPolicy
{
    /** Create a preview. */
    public function preview(User $actor): bool
    {
        return $actor->hasPermission('season_copy.preview');
    }

    /** Execute a previewed copy. */
    public function execute(User $actor): bool
    {
        return $actor->hasPermission('season_copy.execute');
    }

    /** List runs (history). */
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('season_copy.history');
    }

    /**
     * Preview: `season_copy.preview` or `season_copy.history`. Run: `season_copy.history`.
     */
    public function view(User $actor, Model $record): bool
    {
        if (! $this->sameTenant($actor, $record)) {
            return false;
        }

        if ($record instanceof SeasonCopyPreview) {
            return $actor->hasPermission('season_copy.preview') || $actor->hasPermission('season_copy.history');
        }

        return $record instanceof SeasonCopyRun && $actor->hasPermission('season_copy.history');
    }

    private function sameTenant(User $actor, Model $record): bool
    {
        return $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $record->getAttribute('tenant_id');
    }
}
