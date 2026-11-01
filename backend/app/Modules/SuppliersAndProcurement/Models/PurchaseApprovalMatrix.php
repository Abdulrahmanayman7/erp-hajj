<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseApprovalMatrixStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant approval matrix configuration (Phase IV WP01-A).
 * Draft mutations and publish lifecycle are enforced by Actions (WP01-B/C), not here.
 */
class PurchaseApprovalMatrix extends Model implements TenantOwned
{
    use UsesTenantScope;

    /**
     * Lifecycle and authorship fields are set only by domain Actions.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PurchaseApprovalMatrixStatus::class,
            'version_no' => 'integer',
            'effective_from' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === PurchaseApprovalMatrixStatus::Draft;
    }

    public function isPublished(): bool
    {
        return $this->status === PurchaseApprovalMatrixStatus::Published;
    }

    public function isRetired(): bool
    {
        return $this->status === PurchaseApprovalMatrixStatus::Retired;
    }

    public function rules(): HasMany
    {
        return $this->hasMany(PurchaseApprovalMatrixRule::class, 'matrix_id');
    }

    public function levels(): HasMany
    {
        return $this->hasMany(PurchaseApprovalMatrixLevel::class, 'matrix_id')->orderBy('level_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
