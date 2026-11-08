<?php

namespace App\Modules\SeasonalTemplates\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Links a copied target row back to its source and copy run. `copied_hash` is
 * the hash of the field values written by the copy; a later divergence means
 * the row was manually modified and is protected from silent overwrite.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $target_season_id
 * @property string $entity_kind
 * @property int $target_row_id
 * @property string|null $natural_key
 * @property int|null $source_season_id
 * @property int|null $source_template_id
 * @property int|null $source_row_id
 * @property int $copy_run_id
 * @property bool $system_owned
 * @property string $copied_hash
 */
class SeasonCopyRowProvenance extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $table = 'season_copy_row_provenance';

    protected $fillable = [
        'target_season_id',
        'entity_kind',
        'target_row_id',
        'natural_key',
        'source_season_id',
        'source_template_id',
        'source_row_id',
        'copy_run_id',
        'system_owned',
        'copied_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'system_owned' => 'boolean',
        ];
    }
}
