<?php

namespace App\Modules\Meetings\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use Database\Factories\MeetingAgendaItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $meeting_id
 * @property string $title
 * @property string|null $description
 * @property int $sort_order
 */
class MeetingAgendaItem extends Model implements TenantOwned
{
    /** @use HasFactory<MeetingAgendaItemFactory> */
    use HasFactory;

    use UsesTenantScope;

    protected $fillable = [
        'meeting_id',
        'title',
        'description',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    protected static function newFactory(): MeetingAgendaItemFactory
    {
        return MeetingAgendaItemFactory::new();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(MeetingRecommendation::class, 'agenda_item_id');
    }
}
