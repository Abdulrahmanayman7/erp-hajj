<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseDemandGroupRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'season_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'beneficiary_organization_unit_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
