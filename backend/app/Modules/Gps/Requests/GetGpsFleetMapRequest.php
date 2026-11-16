<?php

namespace App\Modules\Gps\Requests;

use App\Core\Shared\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Fleet map markers require explicit gps.map.view (never inferred from provider admin).
 */
class GetGpsFleetMapRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->tenant_id !== null
            && $user->isActive()
            && $user->hasPermission('gps.map.view');
    }

    protected function failedAuthorization(): void
    {
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
