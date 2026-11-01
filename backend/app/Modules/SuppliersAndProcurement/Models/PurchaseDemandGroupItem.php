<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseDemandGroupItem extends Model implements TenantOwned
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
            'pr_version_no_at_link' => 'integer',
            'linked_at' => 'datetime',
            'unlinked_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->unlinked_at === null;
    }

    public function demandGroup(): BelongsTo
    {
        return $this->belongsTo(PurchaseDemandGroup::class, 'demand_group_id');
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function linker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }

    public function unlinker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unlinked_by');
    }
}
