<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Financial authority ceiling for a user. Does not grant approve permission. Phase IV WP01-A.
 */
class PurchaseApproverAuthority extends Model implements TenantOwned
{
    use UsesTenantScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'max_amount',
        'currency',
        'covers_unpriced',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_amount' => 'decimal:2',
            'covers_unpriced' => 'boolean',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
