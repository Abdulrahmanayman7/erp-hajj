<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSupplierDocumentRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reference existence/tenant checks happen in the Action (404 for cross-tenant ids).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'requirement_def_id' => ['required', 'integer'],
            'document_id' => ['required', 'integer'],
            'expires_at' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
