<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPurchaseRequestRequest extends FormRequest
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
            'acknowledge_duplicate_warning' => ['sometimes', 'boolean'],
            'comment' => ['nullable', 'string'],
        ];
    }
}
