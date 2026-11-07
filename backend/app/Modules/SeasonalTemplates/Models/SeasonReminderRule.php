<?php

namespace App\Modules\SeasonalTemplates\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\Notifications\Enums\NotificationSeverity;
use App\Modules\SeasonalTemplates\Enums\SeasonReminderRuleStatus;
use App\Modules\SeasonalTemplates\Enums\SeasonReminderTriggerMode;
use App\Modules\SeasonPilgrims\Models\Season;
use Database\Factories\SeasonReminderRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tenant + season scoped reminder configuration (S08-D11…D16).
 *
 * Recipients are never stored: the scanner resolves the holders of
 * `recipient_permission` at fire time (S08-D14). `status` is only changed by the
 * lifecycle actions, so it is deliberately not mass-assignable.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $season_id
 * @property string $code
 * @property string $title
 * @property string|null $body
 * @property NotificationSeverity $severity
 * @property SeasonReminderRuleStatus $status
 * @property SeasonReminderTriggerMode $trigger_mode
 * @property Carbon|null $trigger_on
 * @property int|null $offset_days
 * @property string $recipient_mode
 * @property string $recipient_permission
 * @property int $created_by
 */
class SeasonReminderRule extends Model implements TenantOwned
{
    /** @use HasFactory<SeasonReminderRuleFactory> */
    use HasFactory;

    use UsesTenantScope;

    public const RECIPIENT_MODE_PERMISSION_HOLDERS = 'permission_holders';

    protected $fillable = [
        'season_id',
        'code',
        'title',
        'body',
        'severity',
        'trigger_mode',
        'trigger_on',
        'offset_days',
        'recipient_mode',
        'recipient_permission',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'severity' => NotificationSeverity::class,
            'status' => SeasonReminderRuleStatus::class,
            'trigger_mode' => SeasonReminderTriggerMode::class,
            'trigger_on' => 'date',
            'offset_days' => 'integer',
        ];
    }

    protected static function newFactory(): SeasonReminderRuleFactory
    {
        return SeasonReminderRuleFactory::new();
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class, 'season_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
