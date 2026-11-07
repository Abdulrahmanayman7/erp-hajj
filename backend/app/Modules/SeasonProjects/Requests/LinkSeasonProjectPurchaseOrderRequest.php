<?php

namespace App\Modules\SeasonProjects\Requests;

use App\Core\Tenancy\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

class LinkSeasonProjectPurchaseOrderRequest extends FormRequest
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
            'purchase_order_id' => ['required', 'integer', new TenantExists('purchase_orders')],
            'notes' => ['nullable', 'string'],
        ];
    }
}
