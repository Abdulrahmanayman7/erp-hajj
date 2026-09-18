<?php

namespace App\Modules\Meetings\Requests;

use App\Core\Tenancy\Validation\TenantExists;
use App\Modules\Meetings\Enums\RecommendationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecommendationRequest extends FormRequest
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
        $isUpdate = in_array($this->method(), ['PATCH', 'PUT'], true);

        return [
            'title' => $isUpdate
                ? ['sometimes', 'required', 'string', 'max:200']
                : ['required', 'string', 'max:200'],
            'description' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'string', 'max:10000'],
            'agenda_item_id' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'integer', new TenantExists('meeting_agenda_items')],
            'owner_employee_id' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'integer', new TenantExists('employees')],
            'status' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'string', Rule::in([
                RecommendationStatus::Draft->value,
                RecommendationStatus::Final->value,
            ])],
            'sort_order' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'integer', 'min:0'],
        ];
    }
}
