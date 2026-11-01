<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePurchaseApprovalDelegationRequest extends FormRequest
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
            'from_user_id' => ['sometimes', 'nullable', 'integer'],
            'to_user_id' => ['required', 'integer'],
            'max_amount' => ['required', 'numeric'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ];
    }
}
