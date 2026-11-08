<?php

namespace App\Modules\OperationalKpis\Requests;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Shared\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

/**
 * Authorization runs first (page 403 before season 422 — D08-Q5), then the
 * explicit `season_id` requirement (D05-C1: no silent Active-season fallback).
 */
class GetOperationalKpisRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && Gate::forUser($user)->allows('viewOperationalKpis');
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user();

        // Page-level denial only: no season, KPI value or SQL in the payload.
        app(AuthorizationSecurity::class)->record(AuthorizationSecurityEvent::OPERATIONAL_KPIS_VIEW_DENIED, [
            'tenant_id' => $user?->tenant_id,
            'actor_id' => $user?->id,
            'entity_type' => 'operational_kpis',
            'permission' => 'operational_kpis.view',
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
