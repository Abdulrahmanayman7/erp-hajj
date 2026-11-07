<?php

namespace App\Modules\SeasonProjects\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sequence row for season project numbers (not TenantOwned — keyed by tenant+season).
 */
class SeasonProjectNumberSequence extends Model
{
    public $incrementing = false;

    protected $table = 'season_project_number_sequences';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'season_id',
        'next_value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'next_value' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }
}
