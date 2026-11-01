<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SuppliersAndProcurement\Enums\EvaluationKind;
use App\Modules\SuppliersAndProcurement\Enums\EvaluationStatus;
use Database\Factories\SupplierEvaluationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierEvaluation extends Model implements TenantOwned
{
    /** @use HasFactory<SupplierEvaluationFactory> */
    use HasFactory;

    use UsesTenantScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'notes',
        'monitoring_recommended',
        'draft_scores',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EvaluationStatus::class,
            'evaluation_kind' => EvaluationKind::class,
            'total_score' => 'decimal:2',
            'monitoring_recommended' => 'boolean',
            'monitoring_set_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'version_no' => 'integer',
            'criteria_pack_version_no' => 'integer',
            'draft_scores' => 'array',
        ];
    }

    protected static function newFactory(): SupplierEvaluationFactory
    {
        return SupplierEvaluationFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (SupplierEvaluation $evaluation): void {
            $original = $evaluation->getOriginal('status');
            $status = $original instanceof EvaluationStatus
                ? $original
                : EvaluationStatus::tryFrom((string) $original);

            if (! in_array($status, [EvaluationStatus::Approved, EvaluationStatus::Rejected], true)) {
                return;
            }

            $allowed = ['updated_at'];
            $dirty = array_keys($evaluation->getDirty());
            if (array_diff($dirty, $allowed) !== []) {
                throw new \LogicException('Approved/rejected evaluations are immutable.');
            }
        });
    }

    public function isDraft(): bool
    {
        return $this->status === EvaluationStatus::Draft;
    }

    public function isPendingApproval(): bool
    {
        return $this->status === EvaluationStatus::PendingApproval;
    }

    public function isApproved(): bool
    {
        return $this->status === EvaluationStatus::Approved;
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SupplierCategory::class, 'supplier_category_id');
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function criteriaPack(): BelongsTo
    {
        return $this->belongsTo(SupplierEvaluationCriteriaPack::class, 'criteria_pack_id');
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function monitoringSetter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'monitoring_set_by');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_evaluation_id');
    }

    public function originAppeal(): BelongsTo
    {
        return $this->belongsTo(SupplierEvaluationAppeal::class, 'origin_appeal_id');
    }

    public function scoreSnapshots(): HasMany
    {
        return $this->hasMany(SupplierEvaluationScoreSnapshot::class, 'evaluation_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function appeals(): HasMany
    {
        return $this->hasMany(SupplierEvaluationAppeal::class, 'evaluation_id');
    }
}
