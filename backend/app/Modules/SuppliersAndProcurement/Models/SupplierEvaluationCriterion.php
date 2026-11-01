<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierEvaluationCriterion extends Model implements TenantOwned
{
    use UsesTenantScope;

    protected $table = 'supplier_evaluation_criteria';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'weight',
        'sort_order',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'decimal:4',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function pack(): BelongsTo
    {
        return $this->belongsTo(SupplierEvaluationCriteriaPack::class, 'pack_id');
    }
}
