<?php

namespace App\Modules\Users\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignUserRolesRequest extends FormRequest
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
            'role_ids' => ['present', 'array'],
            'role_ids.*' => ['integer'],
        ];
    }
}
