<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertPurchaseApprovalSlaPolicyRequest extends FormRequest
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
            'priority' => ['required', 'string', Rule::in(array_column(PurchaseRequestPriority::cases(), 'value'))],
            'level_order' => ['required', 'integer', 'min:1'],
            'target_hours' => ['required', 'integer', 'min:1'],
            'reminder_offset_hours' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
