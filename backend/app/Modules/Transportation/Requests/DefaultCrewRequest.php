<?php

namespace App\Modules\Transportation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DefaultCrewRequest extends FormRequest
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
            'default_driver_employee_id' => ['present', 'nullable', 'integer', 'prohibits:default_external_driver_id'],
            'default_external_driver_id' => ['present', 'nullable', 'integer'],
            'default_supervisor_employee_id' => ['present', 'nullable', 'integer'],
        ];
    }
}
