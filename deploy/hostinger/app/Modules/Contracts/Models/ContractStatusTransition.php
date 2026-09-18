<?php

namespace App\Modules\Contracts\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\Contracts\Enums\ContractStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only contract lifecycle history row.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $contract_id
 * @property string|null $from_status
 * @property string $to_status
 * @property int|null $actor_user_id
 * @property string|null $comment
 * @property string|null $correlation_id
 * @property Carbon $created_at
 */
class ContractStatusTransition extends Model implements TenantOwned
{
    use UsesTenantScope;

    public $timestamps = false;

    protected $fillable = [
        'contract_id',
        'from_status',
        'to_status',
        'actor_user_id',
        'comment',
        'correlation_id',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => ContractStatus::class,
            'to_status' => ContractStatus::class,
            'created_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
