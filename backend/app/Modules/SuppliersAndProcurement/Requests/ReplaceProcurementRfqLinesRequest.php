<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceProcurementRfqLinesRequest extends FormRequest
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
            'lines.*.purchase_request_line_id' => ['nullable', 'integer'],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.specifications' => ['nullable', 'string', 'max:5000'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit' => ['required', 'string', 'max:64'],
            'lines.*.need_date' => ['nullable', 'date'],
            'lines.*.inventory_item_id' => ['nullable', 'integer'],
        ];
    }
}
