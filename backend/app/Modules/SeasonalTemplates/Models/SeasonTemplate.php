<?php

namespace App\Modules\SeasonalTemplates\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\SeasonalTemplates\Enums\TemplateStatus;
use App\Modules\SeasonalTemplates\Support\SeasonTemplateNumberGenerator;
use Database\Factories\SeasonTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $template_number
 * @property string $name
 * @property string|null $description
 * @property TemplateStatus $status
 * @property string $matrix_version
 * @property int $created_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $archived_at
 */
class SeasonTemplate extends Model implements TenantOwned
{
    /** @use HasFactory<SeasonTemplateFactory> */
    use HasFactory;

    use UsesTenantScope;

    protected $fillable = [
        'template_number',
        'name',
        'description',
        'status',
        'matrix_version',
        'created_by',
        'approved_by',
        'approved_at',
        'archived_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TemplateStatus::class,
            'approved_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    protected static function newFactory(): SeasonTemplateFactory
    {
        return SeasonTemplateFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (SeasonTemplate $template): void {
            $number = $template->getAttribute('template_number');
            if ($number === null || $number === '') {
                $template->setAttribute('template_number', app(SeasonTemplateNumberGenerator::class)->next());
            }
        });
    }

    /**
     * @return HasMany<SeasonTemplateItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SeasonTemplateItem::class, 'season_template_id')->orderBy('sort_order')->orderBy('id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
