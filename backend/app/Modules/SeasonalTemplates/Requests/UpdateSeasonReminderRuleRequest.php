<?php

namespace App\Modules\SeasonalTemplates\Requests;

use App\Modules\SeasonalTemplates\Enums\SeasonReminderTriggerMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSeasonReminderRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `code` is accepted only so an attempt to change it is answered with a stable
     * domain code (SEASON_REMINDER_RULE_CODE_IMMUTABLE) instead of being ignored.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'string', 'max:64'],
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'severity' => ['sometimes', Rule::in(['info', 'warning', 'critical'])],
            'trigger_mode' => ['sometimes', Rule::in(array_column(SeasonReminderTriggerMode::cases(), 'value'))],
            'trigger_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'offset_days' => ['sometimes', 'nullable', 'integer'],
            'recipient_permission' => ['sometimes', 'string', 'max:64'],
        ];
    }
}
