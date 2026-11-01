<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Matrix matching rule (bands, OU/season/kind, unpriced path). Phase IV WP01-A.
 */
class PurchaseApprovalMatrixRule extends Model implements TenantOwned
{
    use UsesTenantScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'min_amount',
        'max_amount',
        'request_kind',
        'applies_when_unpriced',
        'specificity_hint',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'request_kind' => PurchaseRequestKind::class,
            'applies_when_unpriced' => 'boolean',
            'specificity_hint' => 'integer',
        ];
    }

    public function matrix(): BelongsTo
    {
        return $this->belongsTo(PurchaseApprovalMatrix::class, 'matrix_id');
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_id');
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class, 'season_id');
    }
}
