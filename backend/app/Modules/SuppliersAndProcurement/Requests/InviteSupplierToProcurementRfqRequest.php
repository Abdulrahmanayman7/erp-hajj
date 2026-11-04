<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InviteSupplierToProcurementRfqRequest extends FormRequest
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
        ];
    }
}
