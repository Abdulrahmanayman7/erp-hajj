<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertPurchaseApprovalMatrixLevelRequest extends FormRequest
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
        $creating = $this->isMethod('POST');

        return [
            'level_order' => [$creating ? 'required' : 'sometimes', 'integer', 'min:1'],
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
        ];
    }
}
