<?php

namespace App\Modules\Decisions\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\Decisions\Enums\DecisionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only decision lifecycle history row.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $decision_id
 * @property string|null $from_status
 * @property string $to_status
 * @property int|null $performed_by
 * @property string|null $comment
 * @property string $correlation_id
 * @property Carbon $created_at
 */
class DecisionStatusTransition extends Model implements TenantOwned
{
    use UsesTenantScope;

    public $timestamps = false;

    protected $fillable = [
        'decision_id',
        'from_status',
        'to_status',
        'performed_by',
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
            'from_status' => DecisionStatus::class,
            'to_status' => DecisionStatus::class,
            'created_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(Decision::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
