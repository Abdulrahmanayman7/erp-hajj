<?php

namespace App\Modules\ExecutiveDashboard\Requests;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Shared\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

/**
 * Dual page gate runs before season validation (403 beats 422).
 * Explicit season_id only — S10-T01 = A.
 */
class GetExecutiveDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && Gate::forUser($user)->allows('viewExecutiveDashboard');
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user();

        app(AuthorizationSecurity::class)->record(AuthorizationSecurityEvent::EXECUTIVE_DASHBOARD_VIEW_DENIED, [
            'tenant_id' => $user?->tenant_id,
            'actor_id' => $user?->id,
            'entity_type' => 'executive_dashboard',
            'permission' => 'executive_dashboard.view+operational_kpis.view',
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
        return [
            'season_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function seasonId(): int
    {
        return (int) $this->validated('season_id');
    }

    protected function failedValidation(Validator $validator): void
    {
        if (isset($validator->failed()['season_id']['Required'])) {
            throw new HttpResponseException(ApiResponse::error(
                message: 'يجب تحديد الموسم (season_id).',
                code: 'SEASON_REQUIRED',
                errors: $validator->errors()->toArray(),
                status: 422,
            ));
        }

        parent::failedValidation($validator);
    }
}
