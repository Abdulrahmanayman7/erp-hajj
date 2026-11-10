<?php

namespace App\Modules\ExecutiveAlerts\Requests;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AcknowledgeExecutiveAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && Gate::forUser($user)->allows('acknowledgeExecutiveAlert');
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user();

        app(AuthorizationSecurity::class)->record(AuthorizationSecurityEvent::EXECUTIVE_ALERT_VIEW_DENIED, [
            'tenant_id' => $user?->tenant_id,
            'actor_id' => $user?->id,
            'entity_type' => 'executive_alert',
            'permission' => 'executive_dashboard.view+operational_kpis.view+executive_alerts.view',
            'denial_code' => 'permission_denied',
            'outcome' => 'denied',
        ], $this);

        throw new AuthorizationException;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}
