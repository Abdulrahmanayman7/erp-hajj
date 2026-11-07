<?php

namespace App\Modules\SeasonalTemplates\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\SeasonalTemplates\Enums\CopyMode;
use App\Modules\SeasonalTemplates\Enums\CopyRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $run_number
 * @property int $preview_id
 * @property CopyMode $mode
 * @property int $source_season_id
 * @property int|null $source_template_id
 * @property int $target_season_id
 * @property CopyRunStatus $status
 * @property string|null $error_code
 * @property string|null $error_message
 * @property array<string, int>|null $counts_json
 * @property string $correlation_id
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property int $created_by
 */
class SeasonCopyRun extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'run_number',
        'preview_id',
        'mode',
        'source_season_id',
        'source_template_id',
        'target_season_id',
        'status',
        'error_code',
        'error_message',
        'counts_json',
        'correlation_id',
        'started_at',
        'finished_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => CopyMode::class,
            'status' => CopyRunStatus::class,
            'counts_json' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<SeasonCopyRunLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SeasonCopyRunLine::class, 'run_id')->orderBy('id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
