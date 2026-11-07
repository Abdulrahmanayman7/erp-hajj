<?php

namespace App\Modules\SeasonProjects\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonProjects\Enums\SeasonProjectPhaseStatus;
use App\Modules\Tasks\Models\Task;
use Database\Factories\SeasonProjectPhaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $season_id
 * @property int $project_id
 * @property string $title
 * @property int $sort_order
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property SeasonProjectPhaseStatus $status
 */
class SeasonProjectPhase extends Model implements TenantOwned
{
    /** @use HasFactory<SeasonProjectPhaseFactory> */
    use HasFactory;

    use UsesTenantScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'season_id',
        'project_id',
        'title',
        'sort_order',
        'starts_on',
        'ends_on',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SeasonProjectPhaseStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'sort_order' => 'integer',
        ];
    }

    protected static function newFactory(): SeasonProjectPhaseFactory
    {
        return SeasonProjectPhaseFactory::new();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeasonProject::class, 'project_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'phase_id');
    }
}
