<?php

namespace App\Modules\ExecutiveAlerts\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Shared\ApiResponse;
use App\Models\User;
use App\Modules\ExecutiveAlerts\Enums\ExecutiveAlertStatus;
use App\Modules\ExecutiveAlerts\Models\ExecutiveAlert;
use App\Modules\ExecutiveAlerts\Support\ExecutiveAlertVisibility;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Acknowledge an alert (U05 = A: gated by `executive_alerts.view`). The lookup is
 * tenant-scoped and domain-filtered, so foreign / hidden alerts answer 404.
 * Idempotent for an already acknowledged alert; a cleared alert cannot be acknowledged.
 */
final class AcknowledgeExecutiveAlert
{
    public function __construct(private readonly AuthorizationSecurity $security) {}

    public function execute(User $actor, int $alertId, ?Request $request = null): ExecutiveAlert
    {
        Gate::forUser($actor)->authorize('acknowledgeExecutiveAlert');

        return DB::transaction(function () use ($actor, $alertId, $request): ExecutiveAlert {
            /** @var ExecutiveAlert $alert */
            $alert = ExecutiveAlertVisibility::query($actor)
                ->whereKey($alertId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($alert->status === ExecutiveAlertStatus::Cleared) {
                throw new HttpResponseException(ApiResponse::error(
                    message: 'لا يمكن الإقرار بتنبيه تم إغلاقه.',
                    code: 'EXECUTIVE_ALERT_CLEARED',
                    status: 409,
                ));
            }

            if ($alert->status === ExecutiveAlertStatus::Acknowledged) {
                return $alert;
            }

            $alert->forceFill([
                'status' => ExecutiveAlertStatus::Acknowledged,
                'acknowledged_at' => now(),
                'acknowledged_by' => $actor->id,
            ])->save();

            $this->security->record(AuthorizationSecurityEvent::EXECUTIVE_ALERT_ACKNOWLEDGED, [
                'tenant_id' => $actor->tenant_id,
                'actor_id' => $actor->id,
                'entity_type' => 'executive_alert',
                'entity_id' => $alert->id,
                'entity_number' => $alert->rule_code->value,
                'season_id' => $alert->season_id,
                'rule_code' => $alert->rule_code->value,
                'from_status' => ExecutiveAlertStatus::Open->value,
                'to_status' => ExecutiveAlertStatus::Acknowledged->value,
            ], $request);

            return $alert;
        });
    }
}
