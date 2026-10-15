<?php

namespace App\Modules\Transportation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignRunSupervisorRequest extends FormRequest
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
            'supervisor_employee_id' => ['present', 'nullable', 'integer'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
