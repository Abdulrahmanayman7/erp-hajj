<?php

namespace App\Modules\RisksAndComplaints\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\Accommodation\Models\AccommodationFacility;
use App\Modules\Employees\Models\Employee;
use App\Modules\RisksAndComplaints\Enums\ComplaintStatus;
use App\Modules\RisksAndComplaints\Exceptions\RisksAndComplaintsDomainException;
use App\Modules\RisksAndComplaints\Support\OperationalComplaintNumberGenerator;
use App\Modules\SeasonPilgrims\Contracts\SeasonOwned;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroup;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use Database\Factories\OperationalComplaintFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $season_id
 * @property string $complaint_number
 * @property string $title
 * @property string $body
 * @property string $category_code
 * @property ComplaintStatus $status
 * @property int $created_by
 * @property int|null $assignee_employee_id
 * @property int $escalation_level
 * @property int|null $project_id
 */
class OperationalComplaint extends Model implements SeasonOwned, TenantOwned
{
    /** @use HasFactory<OperationalComplaintFactory> */
    use HasFactory;

    use SoftDeletes;
    use UsesTenantScope;

    protected $fillable = [
        'season_id',
        'title',
        'body',
        'category_code',
        'status',
        'created_by',
        'assignee_employee_id',
        'escalation_level',
        'escalated_to_employee_id',
        'escalate_reason',
        'escalated_at',
        'resolution_text',
        'resolved_at',
        'resolved_by',
        'closed_at',
        'closed_by',
        'close_reason',
        'reopened_at',
        'reopened_by',
        'reopen_reason',
        'season_pilgrim_id',
        'group_id',
        'facility_id',
        'transport_season_vehicle_id',
        'project_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ComplaintStatus::class,
            'escalation_level' => 'integer',
            'escalated_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
            'project_id' => 'integer',
        ];
    }

    protected static function newFactory(): OperationalComplaintFactory
    {
        return OperationalComplaintFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (OperationalComplaint $complaint): void {
            $number = $complaint->getAttribute('complaint_number');
            if ($number === null || $number === '') {
                $season = Season::query()->findOrFail((int) $complaint->getAttribute('season_id'));
                $complaint->setAttribute(
                    'complaint_number',
                    app(OperationalComplaintNumberGenerator::class)->next($season),
                );
            }

            if ($complaint->getAttribute('status') === null) {
                $complaint->setAttribute('status', ComplaintStatus::New);
            }

            if ($complaint->getAttribute('escalation_level') === null) {
                $complaint->setAttribute('escalation_level', 0);
            }
        });

        static::deleting(function (OperationalComplaint $complaint): void {
            $status = $complaint->status instanceof ComplaintStatus
                ? $complaint->status
                : ComplaintStatus::from((string) $complaint->getAttribute('status'));

            if (! $status->allowsSoftDelete() || $complaint->assignee_employee_id !== null) {
                throw RisksAndComplaintsDomainException::complaintDeleteNotAllowed();
            }
        });
    }

    public function owningSeasonId(): int
    {
        return (int) $this->season_id;
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assigneeEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assignee_employee_id');
    }

    public function escalatedToEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'escalated_to_employee_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(OperationalComplaintNote::class, 'complaint_id');
    }

    public function seasonPilgrim(): BelongsTo
    {
        return $this->belongsTo(SeasonPilgrim::class, 'season_pilgrim_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(SeasonPilgrimGroup::class, 'group_id');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(AccommodationFacility::class, 'facility_id');
    }

    public function transportSeasonVehicle(): BelongsTo
    {
        return $this->belongsTo(TransportSeasonVehicle::class, 'transport_season_vehicle_id');
    }
}
