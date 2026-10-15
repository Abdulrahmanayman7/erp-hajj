<?php

namespace App\Modules\SeasonPilgrims\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Seasonal identity snapshot (F6, F20). Explicit allow-list of identity
 * values; never follows Pilgrim changes automatically.
 *
 * @property int $id
 * @property int $season_pilgrim_id
 * @property int|null $pilgrim_identity_document_id
 * @property string $full_name
 * @property string|null $gender
 * @property string|null $nationality
 * @property string|null $document_type
 * @property string|null $document_issuing_country
 * @property string|null $document_number
 * @property Carbon|null $locked_at
 */
class SeasonPilgrimIdentitySnapshot extends Model implements TenantOwned
{
    use UsesTenantScope;

    /**
     * Identity values captured in the snapshot (correctable fields).
     *
     * @var list<string>
     */
    public const IDENTITY_FIELDS = [
        'full_name',
        'gender',
        'nationality',
        'document_type',
        'document_issuing_country',
        'document_number',
    ];

    protected $fillable = [
        'season_pilgrim_id',
        'pilgrim_identity_document_id',
        'full_name',
        'gender',
        'nationality',
        'document_type',
        'document_issuing_country',
        'document_number',
        'captured_at',
        'captured_by',
        'locked_at',
        'locked_by',
        'corrected_at',
        'corrected_by',
        'correction_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'locked_at' => 'datetime',
            'corrected_at' => 'datetime',
            'correction_count' => 'integer',
        ];
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function identityValues(): array
    {
        return $this->only(self::IDENTITY_FIELDS);
    }

    public function seasonPilgrim(): BelongsTo
    {
        return $this->belongsTo(SeasonPilgrim::class);
    }
}
