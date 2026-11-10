<?php

namespace App\Modules\SeasonProjects\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $baseline_id
 * @property Carbon $effective_at
 * @property string $planned_progress_percent
 */
class SeasonProjectProgressCheckpoint extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $table = 'season_project_progress_checkpoints';

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime',
            'planned_progress_percent' => 'decimal:2',
        ];
    }

    public function baseline(): BelongsTo
    {
        return $this->belongsTo(SeasonProjectProgressBaseline::class, 'baseline_id');
    }
}
