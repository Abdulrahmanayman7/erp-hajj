<?php

namespace App\Modules\Transportation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Asset / Contract ids are resolved through tenant-scoped queries in the
 * action (a foreign tenant's id is "not found").
 */
class UpdateVehicleLinksRequest extends FormRequest
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
            'asset_id' => ['present', 'nullable', 'integer'],
            'contract_id' => ['present', 'nullable', 'integer'],
        ];
    }
}
