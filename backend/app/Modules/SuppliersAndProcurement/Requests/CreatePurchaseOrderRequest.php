<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePurchaseOrderRequest extends FormRequest
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
            'rfq_id' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'contract_id' => ['nullable', 'integer'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
