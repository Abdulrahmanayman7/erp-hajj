<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Modules\SuppliersAndProcurement\Enums\GeoPlaceKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Platform geographic reference (no tenant_id).
 *
 * @property int $id
 * @property string $country_code
 * @property GeoPlaceKind $kind
 * @property string $code
 * @property string $name_ar
 * @property string $name_en
 * @property int|null $parent_id
 * @property bool $is_active
 */
class GeoPlace extends Model
{
    protected $fillable = [
        'country_code',
        'kind',
        'code',
        'name_ar',
        'name_en',
        'parent_id',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => GeoPlaceKind::class,
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (GeoPlace $place): void {
            if ($place->isDirty(['country_code', 'kind', 'code'])) {
                throw new \LogicException('Geo place identity fields are immutable after seed.');
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
