<?php

namespace App\Modules\SeasonPilgrims\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\SeasonPilgrims\Contracts\SeasonOwned;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $season_id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 */
class SeasonProgram extends Model implements SeasonOwned, TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'season_id',
        'code',
        'name',
        'description',
        'is_active',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function owningSeasonId(): int
    {
        return (int) $this->season_id;
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function seasonPilgrims(): HasMany
    {
        return $this->hasMany(SeasonPilgrim::class);
    }

    public function isUsed(): bool
    {
        return $this->seasonPilgrims()->exists()
            || SeasonPilgrimProgramChange::query()
                ->where(fn ($q) => $q->where('from_season_program_id', $this->id)
                    ->orWhere('to_season_program_id', $this->id))
                ->exists();
    }
}
