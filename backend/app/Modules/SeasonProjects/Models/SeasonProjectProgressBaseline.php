<?php

namespace App\Modules\SeasonProjects\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\SeasonProjects\Enums\SeasonProjectProgressBaselineStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $season_project_id
 * @property int $version
 * @property SeasonProjectProgressBaselineStatus $status
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property int|null $created_by
 */
class SeasonProjectProgressBaseline extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $table = 'season_project_progress_baselines';

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'status' => SeasonProjectProgressBaselineStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeasonProject::class, 'season_project_id');
    }

    public function checkpoints(): HasMany
    {
        return $this->hasMany(SeasonProjectProgressCheckpoint::class, 'baseline_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
