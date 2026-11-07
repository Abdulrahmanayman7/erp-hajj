<?php

namespace App\Modules\RisksAndComplaints\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\Accommodation\Models\AccommodationFacility;
use App\Modules\Employees\Models\Employee;
use App\Modules\RisksAndComplaints\Enums\RiskClosureOutcome;
use App\Modules\RisksAndComplaints\Enums\RiskSeverity;
use App\Modules\RisksAndComplaints\Enums\RiskStatus;
use App\Modules\RisksAndComplaints\Exceptions\RisksAndComplaintsDomainException;
use App\Modules\RisksAndComplaints\Support\OperationalRiskNumberGenerator;
use App\Modules\RisksAndComplaints\Support\RiskScoreCalculator;
use App\Modules\SeasonPilgrims\Contracts\SeasonOwned;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroup;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use Database\Factories\OperationalRiskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $season_id
 * @property string $risk_number
 * @property string $title
 * @property string|null $description
 * @property RiskStatus $status
 * @property int|null $likelihood
 * @property int|null $impact
 * @property int|null $score
 * @property RiskSeverity|null $severity_level
 * @property RiskClosureOutcome|null $closure_outcome
 * @property string|null $close_reason
 * @property int $created_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property int|null $owner_employee_id
 * @property int|null $season_pilgrim_id
 * @property int|null $group_id
 * @property int|null $facility_id
 * @property int|null $transport_season_vehicle_id
 * @property int|null $project_id
 */
class OperationalRisk extends Model implements SeasonOwned, TenantOwned
{
    /** @use HasFactory<OperationalRiskFactory> */
    use HasFactory;

    use SoftDeletes;
    use UsesTenantScope;

    protected $fillable = [
        'season_id',
        'title',
        'description',
        'status',
        'likelihood',
        'impact',
        'score',
        'severity_level',
        'closure_outcome',
        'close_reason',
        'created_by',
        'approved_by',
        'approved_at',
        'owner_employee_id',
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
            'status' => RiskStatus::class,
            'likelihood' => 'integer',
            'impact' => 'integer',
            'score' => 'integer',
            'severity_level' => RiskSeverity::class,
            'closure_outcome' => RiskClosureOutcome::class,
            'approved_at' => 'datetime',
            'project_id' => 'integer',
        ];
    }

    protected static function newFactory(): OperationalRiskFactory
    {
        return OperationalRiskFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (OperationalRisk $risk): void {
            $number = $risk->getAttribute('risk_number');
            if ($number === null || $number === '') {
                $season = Season::query()->findOrFail((int) $risk->getAttribute('season_id'));
                $risk->setAttribute(
                    'risk_number',
                    app(OperationalRiskNumberGenerator::class)->next($season),
                );
            }

            if ($risk->getAttribute('status') === null) {
                $risk->setAttribute('status', RiskStatus::Identified);
            }
        });

        static::saving(function (OperationalRisk $risk): void {
            $likelihood = $risk->getAttribute('likelihood');
            $impact = $risk->getAttribute('impact');
            if ($likelihood !== null && $impact !== null) {
                $computed = app(RiskScoreCalculator::class)->calculate((int) $likelihood, (int) $impact);
                $risk->setAttribute('score', $computed['score']);
                $risk->setAttribute('severity_level', $computed['severity']);
            }
        });

        static::deleting(function (OperationalRisk $risk): void {
            $status = $risk->status instanceof RiskStatus
                ? $risk->status
                : RiskStatus::from((string) $risk->getAttribute('status'));

            if (! $status->allowsSoftDelete()) {
                throw RisksAndComplaintsDomainException::riskDeleteNotAllowed();
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

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function ownerEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'owner_employee_id');
    }

    public function treatment(): HasOne
    {
        return $this->hasOne(OperationalRiskTreatment::class, 'risk_id');
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
