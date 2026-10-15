<?php

namespace App\Modules\SeasonPilgrims\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only program assignment history (F22, F24).
 */
class SeasonPilgrimProgramChange extends Model implements TenantOwned
{
    use UsesTenantScope;

    public const UPDATED_AT = null;

    protected $fillable = [
        'season_id',
        'season_pilgrim_id',
        'from_season_program_id',
        'to_season_program_id',
        'reason',
        'changed_by',
        'correlation_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): bool => false);
    }

    public function fromProgram(): BelongsTo
    {
        return $this->belongsTo(SeasonProgram::class, 'from_season_program_id');
    }

    public function toProgram(): BelongsTo
    {
        return $this->belongsTo(SeasonProgram::class, 'to_season_program_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
