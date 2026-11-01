<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierEvaluationScoreSnapshot extends Model implements TenantOwned
{
    use UsesTenantScope;

    /**
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'decimal:4',
            'score' => 'decimal:2',
            'sort_order' => 'integer',
            'source_criterion_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \LogicException('Evaluation score snapshots are immutable.');
        });

        static::deleting(function (): void {
            throw new \LogicException('Evaluation score snapshots cannot be deleted.');
        });
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(SupplierEvaluation::class, 'evaluation_id');
    }
}
