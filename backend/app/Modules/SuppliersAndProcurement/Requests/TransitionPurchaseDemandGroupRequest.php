<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionPurchaseDemandGroupRequest extends FormRequest
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
            'to' => ['required', 'string', Rule::in(['draft', 'active', 'frozen', 'closed'])],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
