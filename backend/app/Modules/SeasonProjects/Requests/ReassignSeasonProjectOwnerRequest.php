<?php

namespace App\Modules\SeasonProjects\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReassignSeasonProjectOwnerRequest extends FormRequest
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
            'owner_employee_id' => ['required', 'integer'],
        ];
    }
}
