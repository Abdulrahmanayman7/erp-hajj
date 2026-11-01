<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePendingSupplierBankAccountRequest extends FormRequest
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
            'account_holder_name' => ['sometimes', 'string', 'max:255'],
            'bank_name' => ['sometimes', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:64'],
            'account_number' => ['nullable', 'string', 'max:64'],
            'currency' => ['nullable', 'string', 'size:3'],
            'evidence_document_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function bankingPayload(): array
    {
        return $this->safe()->only([
            'account_holder_name',
            'bank_name',
            'label',
            'iban',
            'account_number',
            'currency',
            'evidence_document_id',
        ]);
    }
}
