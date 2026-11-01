<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\EvaluationAppealStatus;
use Database\Factories\SupplierEvaluationAppealFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierEvaluationAppeal extends Model implements TenantOwned
{
    /** @use HasFactory<SupplierEvaluationAppealFactory> */
    use HasFactory;

    use UsesTenantScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reason',
        'review_outcome_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EvaluationAppealStatus::class,
            'filed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'open_slot' => 'integer',
        ];
    }

    protected static function newFactory(): SupplierEvaluationAppealFactory
    {
        return SupplierEvaluationAppealFactory::new();
    }

    public function isOpen(): bool
    {
        return $this->status === EvaluationAppealStatus::Open;
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(SupplierEvaluation::class, 'evaluation_id');
    }

    public function filer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filed_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function resultingEvaluation(): BelongsTo
    {
        return $this->belongsTo(SupplierEvaluation::class, 'resulting_evaluation_id');
    }
}
