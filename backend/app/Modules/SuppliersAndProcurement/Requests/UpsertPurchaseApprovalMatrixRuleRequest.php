<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertPurchaseApprovalMatrixRuleRequest extends FormRequest
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
            'min_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'max_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'request_kind' => ['sometimes', 'nullable', 'string', Rule::enum(PurchaseRequestKind::class)],
            'organization_unit_id' => ['sometimes', 'nullable', 'integer'],
            'season_id' => ['sometimes', 'nullable', 'integer'],
            'applies_when_unpriced' => ['sometimes', 'boolean'],
        ];
    }
}
