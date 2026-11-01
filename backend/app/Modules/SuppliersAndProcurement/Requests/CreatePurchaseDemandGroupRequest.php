<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePurchaseDemandGroupRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'season_id' => ['nullable', 'integer', 'min:1'],
            'beneficiary_organization_unit_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
