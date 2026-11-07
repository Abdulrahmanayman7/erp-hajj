<?php

namespace App\Modules\SeasonProjects\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrder;
use Database\Factories\SeasonProjectBudgetPoLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Links one PO header to at most one project budget (E-Q06).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $season_id
 * @property int $project_id
 * @property int $purchase_order_id
 * @property string|null $commitment_amount
 * @property string|null $actual_amount
 * @property string $currency_code
 * @property Carbon $linked_at
 * @property int|null $linked_by
 * @property string|null $notes
 */
class SeasonProjectBudgetPoLink extends Model implements TenantOwned
{
    /** @use HasFactory<SeasonProjectBudgetPoLinkFactory> */
    use HasFactory;

    use UsesTenantScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'season_id',
        'project_id',
        'purchase_order_id',
        'commitment_amount',
        'actual_amount',
        'currency_code',
        'linked_at',
        'linked_by',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'commitment_amount' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'linked_at' => 'datetime',
        ];
    }

    protected static function newFactory(): SeasonProjectBudgetPoLinkFactory
    {
        return SeasonProjectBudgetPoLinkFactory::new();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeasonProject::class, 'project_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function linker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }
}
