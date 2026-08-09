<?php

namespace App\Modules\Meetings\Requests;

use App\Core\Tenancy\Validation\TenantExists;
use App\Modules\Meetings\Enums\MeetingLocationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateMeetingRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'location_type' => ['nullable', 'string', Rule::in([
                MeetingLocationType::Physical->value,
                MeetingLocationType::Remote->value,
                MeetingLocationType::Hybrid->value,
            ])],
            'location_text' => ['nullable', 'string', 'max:255'],
            'meeting_link' => ['nullable', 'string', 'max:500'],
            'organization_unit_id' => ['nullable', 'integer', new TenantExists('organization_units')],
            'chairperson_employee_id' => ['nullable', 'integer', new TenantExists('employees')],
            'secretary_employee_id' => ['nullable', 'integer', new TenantExists('employees')],
            'notes' => ['nullable', 'string', 'max:10000'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
