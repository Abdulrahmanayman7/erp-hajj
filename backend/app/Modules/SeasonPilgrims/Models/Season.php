<?php

namespace App\Modules\SeasonPilgrims\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\SeasonPilgrims\Enums\SeasonStatus;
use Database\Factories\SeasonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property int $hajj_year
 * @property Carbon $operational_starts_on
 * @property Carbon $operational_ends_on
 * @property SeasonStatus $status
 * @property Carbon|null $first_activated_at
 */
class Season extends Model implements TenantOwned
{
    /** @use HasFactory<SeasonFactory> */
    use HasFactory;

    use UsesTenantScope;

    protected $fillable = [
        'code',
        'name',
        'hajj_year',
        'operational_starts_on',
        'operational_ends_on',
        'status',
        'notes',
        'first_activated_at',
        'activated_at',
        'activated_by',
        'closed_at',
        'closed_by',
        'reopened_at',
        'reopened_by',
        'created_by',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['active_flag'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SeasonStatus::class,
            'hajj_year' => 'integer',
            'operational_starts_on' => 'date',
            'operational_ends_on' => 'date',
            'first_activated_at' => 'datetime',
            'activated_at' => 'datetime',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    protected static function newFactory(): SeasonFactory
    {
        return SeasonFactory::new();
    }

    public function isWritable(): bool
    {
        return $this->status->isWritable();
    }

    public function seasonPilgrims(): HasMany
    {
        return $this->hasMany(SeasonPilgrim::class);
    }

    public function programs(): HasMany
    {
        return $this->hasMany(SeasonProgram::class);
    }

    public function familyGroups(): HasMany
    {
        return $this->hasMany(SeasonFamilyGroup::class);
    }

    public function pilgrimGroups(): HasMany
    {
        return $this->hasMany(SeasonPilgrimGroup::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
