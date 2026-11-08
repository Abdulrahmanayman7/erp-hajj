<?php

namespace App\Modules\SeasonalTemplates\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $preview_id
 * @property string $entity_kind
 * @property string $action
 * @property string $natural_key
 * @property array<string, mixed>|null $summary_json
 * @property string|null $conflict_code
 * @property string|null $proposed_resolution
 */
class SeasonCopyPreviewLine extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'preview_id',
        'entity_kind',
        'action',
        'natural_key',
        'summary_json',
        'conflict_code',
        'proposed_resolution',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'summary_json' => 'array',
        ];
    }
}
