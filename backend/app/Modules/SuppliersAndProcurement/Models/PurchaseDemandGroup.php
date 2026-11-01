<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseDemandGroupStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseDemandGroup extends Model implements TenantOwned
{
    use SoftDeletes;
    use UsesTenantScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PurchaseDemandGroupStatus::class,
            'frozen_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseDemandGroupItem::class, 'demand_group_id')->orderByDesc('id');
    }

    public function activeMemberships(): HasMany
    {
        return $this->hasMany(PurchaseDemandGroupActiveMembership::class, 'demand_group_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function freezer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'frozen_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function beneficiaryUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'beneficiary_organization_unit_id');
    }
}
