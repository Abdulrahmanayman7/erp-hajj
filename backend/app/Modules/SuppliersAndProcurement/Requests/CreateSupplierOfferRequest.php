<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSupplierOfferRequest extends FormRequest
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
            'supplier_id' => ['required', 'integer'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.procurement_rfq_line_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'lines.*.lead_time_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ];
    }
}
