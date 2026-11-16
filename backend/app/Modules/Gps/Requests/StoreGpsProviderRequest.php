<?php

namespace App\Modules\Gps\Requests;

use App\Modules\Gps\Enums\GpsConnectionMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGpsProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9_-]*$/'],
            'connection_method' => ['required', 'string', Rule::in(GpsConnectionMethod::values())],
            'is_enabled' => ['sometimes', 'boolean'],
            'poll_interval_seconds' => ['nullable', 'integer', 'min:30', 'max:86400'],
            'settings' => ['sometimes', 'array'],
            'settings.endpoint_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'settings.base_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'settings.auth_type' => ['sometimes', 'nullable', 'string', 'max:32'],
            'settings.items_path' => ['sometimes', 'nullable', 'string', 'max:120'],
            'settings.field_map' => ['sometimes', 'nullable', 'array'],
            'settings.timeout_seconds' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:30'],
            'settings.require_explicit_consent' => ['sometimes', 'boolean'],
            'settings.tracking_enabled' => ['sometimes', 'boolean'],
            'settings.base_latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'settings.base_longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'settings.radius_km' => ['sometimes', 'nullable', 'numeric', 'min:0.1', 'max:50'],
            'settings.simulate_failure' => ['sometimes', 'boolean'],
            'settings.offline_device_ids' => ['sometimes', 'array'],
            'settings.offline_device_ids.*' => ['string', 'max:128'],
            'credentials' => ['sometimes', 'nullable', 'array'],
            'credentials.webhook_secret' => ['sometimes', 'nullable', 'string', 'max:255'],
            'credentials.api_token' => ['sometimes', 'nullable', 'string', 'max:255'],
            'credentials.bearer_token' => ['sometimes', 'nullable', 'string', 'max:255'],
            'credentials.api_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'credentials.email' => ['sometimes', 'nullable', 'string', 'max:255'],
            'credentials.username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'credentials.password' => ['sometimes', 'nullable', 'string', 'max:255'],
            'credentials.token' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
