<?php

namespace App\Modules\Meetings\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\Employees\Models\Employee;
use App\Modules\Meetings\Enums\MeetingLocationType;
use App\Modules\Meetings\Enums\MeetingStatus;
use App\Modules\Meetings\Support\MeetingNumberGenerator;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use Database\Factories\MeetingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Tenant-owned meeting record.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $meeting_number
 * @property string $title
 * @property string|null $description
 * @property MeetingStatus $status
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property MeetingLocationType $location_type
 * @property string|null $location_text
 * @property string|null $meeting_link
 * @property int|null $organization_unit_id
 * @property int|null $chairperson_employee_id
 * @property int|null $secretary_employee_id
 * @property string|null $minutes_body
 * @property string|null $notes
 * @property int $created_by
 */
class Meeting extends Model implements TenantOwned
{
    /** @use HasFactory<MeetingFactory> */
    use HasFactory;

    use UsesTenantScope;

    protected $fillable = [
        'title',
        'description',
        'status',
        'scheduled_at',
        'started_at',
        'ended_at',
        'location_type',
        'location_text',
        'meeting_link',
        'organization_unit_id',
        'chairperson_employee_id',
        'secretary_employee_id',
        'minutes_body',
        'notes',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MeetingStatus::class,
            'location_type' => MeetingLocationType::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    protected static function newFactory(): MeetingFactory
    {
        return MeetingFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (Meeting $meeting): void {
            $number = $meeting->getAttribute('meeting_number');
            if ($number === null || $number === '') {
                $meeting->setAttribute(
                    'meeting_number',
                    app(MeetingNumberGenerator::class)->next(),
                );
            }
        });

        static::updating(function (Meeting $meeting): void {
            if ($meeting->isDirty('meeting_number')) {
                throw new \LogicException('Meeting number is immutable after creation.');
            }
            if ($meeting->isDirty('tenant_id')) {
                throw new \LogicException('Meeting tenant_id is immutable.');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_id');
    }

    public function chairperson(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'chairperson_employee_id');
    }

    public function secretary(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'secretary_employee_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusTransitions(): HasMany
    {
        return $this->hasMany(MeetingStatusTransition::class)->orderBy('created_at')->orderBy('id');
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(MeetingAttendee::class)->orderBy('id');
    }

    public function agendaItems(): HasMany
    {
        return $this->hasMany(MeetingAgendaItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(MeetingRecommendation::class)->orderBy('sort_order')->orderBy('id');
    }

    public function isMutable(): bool
    {
        return $this->status->isMutable();
    }

    public function isUpcoming(): bool
    {
        if ($this->status !== MeetingStatus::Scheduled || $this->scheduled_at === null) {
            return false;
        }

        $tenant = app(TenantContext::class)->get();
        $timezone = $tenant?->timezone ?: config('app.timezone', 'Asia/Riyadh');

        return $this->scheduled_at->greaterThanOrEqualTo(now($timezone));
    }
}
