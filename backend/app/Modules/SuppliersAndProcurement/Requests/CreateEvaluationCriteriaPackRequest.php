<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use App\Core\Tenancy\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

class CreateEvaluationCriteriaPackRequest extends FormRequest
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
            'supplier_category_id' => ['required', 'integer', new TenantExists('supplier_categories')],
            'notes' => ['nullable', 'string', 'max:5000'],
            'supersedes_pack_id' => ['nullable', 'integer', new TenantExists('supplier_evaluation_criteria_packs')],
        ];
    }
}
