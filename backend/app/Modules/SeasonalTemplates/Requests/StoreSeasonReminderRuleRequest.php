<?php

namespace App\Modules\SeasonalTemplates\Requests;

use App\Modules\SeasonalTemplates\Enums\SeasonReminderTriggerMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shape only. `recipient_permission` (allowlist), trigger consistency, season state and
 * `recipient_mode` (always `permission_holders`) are enforced by the action; `status`,
 * `season_id` and `tenant_id` are never accepted from the payload.
 */
class StoreSeasonReminderRuleRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:64'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:1000'],
            'severity' => ['required', Rule::in(['info', 'warning', 'critical'])],
            'trigger_mode' => ['required', Rule::in(array_column(SeasonReminderTriggerMode::cases(), 'value'))],
            'trigger_on' => ['nullable', 'date_format:Y-m-d'],
            'offset_days' => ['nullable', 'integer'],
            'recipient_permission' => ['required', 'string', 'max:64'],
        ];
    }
}
