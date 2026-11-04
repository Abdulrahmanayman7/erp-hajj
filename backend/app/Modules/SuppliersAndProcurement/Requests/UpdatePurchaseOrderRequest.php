<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseOrderRequest extends FormRequest
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
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'contract_id' => ['sometimes', 'nullable', 'integer'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
        ];
    }
}
