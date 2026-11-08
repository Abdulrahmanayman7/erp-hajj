<?php

namespace App\Modules\SeasonalTemplates\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $run_id
 * @property string $entity_kind
 * @property string $natural_key
 * @property string $result
 * @property string|null $reason_code
 * @property int|null $source_row_id
 * @property int|null $target_row_id
 */
class SeasonCopyRunLine extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'run_id',
        'entity_kind',
        'natural_key',
        'result',
        'reason_code',
        'source_row_id',
        'target_row_id',
    ];
}
