<?php

namespace App\Modules\ExecutiveAlerts\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\ExecutiveAlerts\Enums\ExecutiveAlertRule;
use App\Modules\ExecutiveAlerts\Enums\ExecutiveAlertSeverity;
use App\Modules\ExecutiveAlerts\Enums\ExecutiveAlertStatus;
use App\Modules\SeasonPilgrims\Contracts\SeasonOwned;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Operational alert row (S10 A4). Written only by the scanner (system) and the
 * acknowledge action — never mass-assigned from a request.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $season_id
 * @property ExecutiveAlertRule $rule_code
 * @property string $dedupe_key
 * @property ExecutiveAlertStatus $status
 * @property string $title
 * @property string|null $body
 * @property ExecutiveAlertSeverity $severity
 * @property string|null $source_entity_type
 * @property int|null $source_entity_id
 * @property array<string, mixed>|null $meta
 * @property Carbon $opened_at
 * @property Carbon $last_seen_at
 * @property Carbon|null $acknowledged_at
 * @property int|null $acknowledged_by
 * @property Carbon|null $cleared_at
 */
class ExecutiveAlert extends Model implements SeasonOwned, TenantOwned
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
            'rule_code' => ExecutiveAlertRule::class,
            'status' => ExecutiveAlertStatus::class,
            'severity' => ExecutiveAlertSeverity::class,
            'source_entity_id' => 'integer',
            'meta' => 'array',
            'opened_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'cleared_at' => 'datetime',
        ];
    }

    public function owningSeasonId(): int
    {
        return (int) $this->season_id;
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
