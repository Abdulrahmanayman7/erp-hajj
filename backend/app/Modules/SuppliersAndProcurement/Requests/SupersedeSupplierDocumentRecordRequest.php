<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupersedeSupplierDocumentRecordRequest extends FormRequest
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
            'requirement_def_id' => ['sometimes', 'integer'],
            'document_id' => ['required', 'integer'],
            'expires_at' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
