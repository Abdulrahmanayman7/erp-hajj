<?php

namespace App\Modules\Meetings\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\Employees\Models\Employee;
use App\Modules\Meetings\Enums\RecommendationStatus;
use Database\Factories\MeetingRecommendationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $meeting_id
 * @property int|null $agenda_item_id
 * @property string $title
 * @property string|null $description
 * @property RecommendationStatus $status
 * @property int|null $owner_employee_id
 * @property int $sort_order
 * @property int $created_by
 */
class MeetingRecommendation extends Model implements TenantOwned
{
    /** @use HasFactory<MeetingRecommendationFactory> */
    use HasFactory;

    use UsesTenantScope;

    protected $fillable = [
        'meeting_id',
        'agenda_item_id',
        'title',
        'description',
        'status',
        'owner_employee_id',
        'sort_order',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RecommendationStatus::class,
            'sort_order' => 'integer',
        ];
    }

    protected static function newFactory(): MeetingRecommendationFactory
    {
        return MeetingRecommendationFactory::new();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(MeetingAgendaItem::class, 'agenda_item_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'owner_employee_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
