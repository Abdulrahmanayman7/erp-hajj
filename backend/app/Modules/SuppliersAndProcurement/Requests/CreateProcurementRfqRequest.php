<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateProcurementRfqRequest extends FormRequest
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
            'purchase_request_id' => ['required', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['sometimes', 'array', 'min:1'],
            'lines.*.purchase_request_line_id' => ['nullable', 'integer'],
            'lines.*.description' => ['required_with:lines', 'string', 'max:500'],
            'lines.*.specifications' => ['nullable', 'string', 'max:5000'],
            'lines.*.quantity' => ['required_with:lines', 'numeric', 'gt:0'],
            'lines.*.unit' => ['required_with:lines', 'string', 'max:64'],
            'lines.*.need_date' => ['nullable', 'date'],
            'lines.*.inventory_item_id' => ['nullable', 'integer'],
        ];
    }
}
