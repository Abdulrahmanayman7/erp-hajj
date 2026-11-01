<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ordered approval level under a matrix. Pool eligibility is runtime (no assignee). Phase IV WP01-A.
 */
class PurchaseApprovalMatrixLevel extends Model implements TenantOwned
{
    use UsesTenantScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'level_order',
        'name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level_order' => 'integer',
        ];
    }

    public function matrix(): BelongsTo
    {
        return $this->belongsTo(PurchaseApprovalMatrix::class, 'matrix_id');
    }
}
