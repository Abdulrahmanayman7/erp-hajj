<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReplacePurchaseRequestLinesRequest extends FormRequest
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
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_no' => ['nullable', 'integer', 'min:1'],
            'lines.*.description' => ['required', 'string'],
            'lines.*.specifications' => ['nullable', 'string'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit' => ['required', 'string', 'max:64'],
            'lines.*.need_date' => ['required', 'date'],
            'lines.*.inventory_item_id' => ['nullable', 'integer'],
            'lines.*.unit_estimate' => ['nullable', 'numeric', 'min:0'],
            'lines.*.line_estimate' => ['nullable', 'numeric', 'min:0'],
            'lines.*.unpriced_reason' => ['nullable', 'string'],
        ];
    }
}
