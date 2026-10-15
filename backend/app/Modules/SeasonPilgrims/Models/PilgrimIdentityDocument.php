<?php

namespace App\Modules\SeasonPilgrims\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\SeasonPilgrims\Enums\IdentityDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $pilgrim_id
 * @property IdentityDocumentType $document_type
 * @property string $issuing_country
 * @property string $document_number
 * @property string $normalized_number
 */
class PilgrimIdentityDocument extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $fillable = [
        'pilgrim_id',
        'document_type',
        'issuing_country',
        'document_number',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => IdentityDocumentType::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PilgrimIdentityDocument $document): void {
            $document->setAttribute('issuing_country', mb_strtoupper(trim((string) $document->issuing_country)));
            $document->setAttribute('normalized_number', self::normalizeNumber((string) $document->document_number));
        });
    }

    /**
     * Case/whitespace/dash-insensitive form used for duplicate detection.
     */
    public static function normalizeNumber(string $number): string
    {
        return mb_strtoupper((string) preg_replace('/[\s\-]+/u', '', $number));
    }

    public function pilgrim(): BelongsTo
    {
        return $this->belongsTo(Pilgrim::class);
    }
}
