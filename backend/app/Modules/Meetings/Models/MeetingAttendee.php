<?php

namespace App\Modules\Meetings\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\Employees\Models\Employee;
use App\Modules\Meetings\Enums\MeetingAttendanceStatus;
use Database\Factories\MeetingAttendeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $meeting_id
 * @property int $employee_id
 * @property MeetingAttendanceStatus $attendance_status
 */
class MeetingAttendee extends Model implements TenantOwned
{
    /** @use HasFactory<MeetingAttendeeFactory> */
    use HasFactory;

    use UsesTenantScope;

    protected $fillable = [
        'meeting_id',
        'employee_id',
        'attendance_status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attendance_status' => MeetingAttendanceStatus::class,
        ];
    }

    protected static function newFactory(): MeetingAttendeeFactory
    {
        return MeetingAttendeeFactory::new();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
