<?php

namespace App\Modules\SeasonalTemplates\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\SeasonalTemplates\Enums\CopyMode;
use App\Modules\SeasonalTemplates\Enums\PreviewStatus;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $preview_token
 * @property CopyMode $mode
 * @property int $source_season_id
 * @property int|null $source_template_id
 * @property int $target_season_id
 * @property string $matrix_version
 * @property string $content_hash
 * @property string $source_fingerprint
 * @property string $target_fingerprint
 * @property array<string, string> $adapter_fingerprints_json
 * @property array<string, mixed>|null $date_mapping_json
 * @property array<string, mixed> $selection_json
 * @property array<string, int> $counts_json
 * @property PreviewStatus $status
 * @property Carbon $expires_at
 * @property string|null $invalidated_reason
 * @property int $created_by
 */
class SeasonCopyPreview extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'preview_token',
        'mode',
        'source_season_id',
        'source_template_id',
        'target_season_id',
        'matrix_version',
        'content_hash',
        'source_fingerprint',
        'target_fingerprint',
        'adapter_fingerprints_json',
        'date_mapping_json',
        'selection_json',
        'counts_json',
        'status',
        'expires_at',
        'invalidated_reason',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => CopyMode::class,
            'status' => PreviewStatus::class,
            'adapter_fingerprints_json' => 'array',
            'date_mapping_json' => 'array',
            'selection_json' => 'array',
            'counts_json' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<SeasonCopyPreviewLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SeasonCopyPreviewLine::class, 'preview_id')->orderBy('id');
    }

    public function sourceSeason(): BelongsTo
    {
        return $this->belongsTo(Season::class, 'source_season_id');
    }

    public function targetSeason(): BelongsTo
    {
        return $this->belongsTo(Season::class, 'target_season_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SeasonTemplate::class, 'source_template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
