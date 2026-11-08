<?php

namespace App\Modules\SeasonalTemplates\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $season_template_id
 * @property string $entity_kind
 * @property int $sort_order
 * @property array<string, mixed>|null $selection_json
 * @property array{anchor: string|null, rows: array<string, array<string, mixed>>}|null $snapshot_json
 */
class SeasonTemplateItem extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'season_template_id',
        'entity_kind',
        'sort_order',
        'selection_json',
        'snapshot_json',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'selection_json' => 'array',
            'snapshot_json' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SeasonTemplate::class, 'season_template_id');
    }
}
