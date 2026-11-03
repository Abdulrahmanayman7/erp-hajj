<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierOfferRequest extends FormRequest
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
            'currency' => ['sometimes', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'lines' => ['sometimes', 'array', 'min:1'],
            'lines.*.procurement_rfq_line_id' => ['required_with:lines', 'integer'],
            'lines.*.quantity' => ['required_with:lines', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required_with:lines', 'numeric', 'gte:0'],
            'lines.*.lead_time_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ];
    }
}
