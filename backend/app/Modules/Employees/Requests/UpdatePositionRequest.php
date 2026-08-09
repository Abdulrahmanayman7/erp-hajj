<?php

namespace App\Modules\Employees\Requests;

use App\Core\Tenancy\Validation\TenantUnique;
use App\Modules\Employees\Models\Position;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePositionRequest extends FormRequest
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
        /** @var Position $position */
        $position = $this->route('position');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                (new TenantUnique('positions', 'name'))->ignore($position->id),
            ],
            'code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
