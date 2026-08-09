<?php

namespace App\Modules\Meetings\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\Meetings\Enums\MeetingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only meeting lifecycle history row.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $meeting_id
 * @property string|null $from_status
 * @property string $to_status
 * @property int|null $actor_user_id
 * @property string|null $comment
 * @property string|null $correlation_id
 * @property Carbon $created_at
 */
class MeetingStatusTransition extends Model implements TenantOwned
{
    use UsesTenantScope;

    public $timestamps = false;

    protected $fillable = [
        'meeting_id',
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
            'from_status' => MeetingStatus::class,
            'to_status' => MeetingStatus::class,
            'created_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
