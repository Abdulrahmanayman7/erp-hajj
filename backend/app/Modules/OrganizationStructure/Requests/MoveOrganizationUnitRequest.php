<?php

namespace App\Modules\OrganizationStructure\Requests;

use App\Core\Tenancy\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

class MoveOrganizationUnitRequest extends FormRequest
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
            'parent_id' => ['present', 'nullable', 'integer', new TenantExists('organization_units')],
        ];
    }
}
