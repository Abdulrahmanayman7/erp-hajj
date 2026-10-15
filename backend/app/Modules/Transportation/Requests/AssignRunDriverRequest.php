<?php

namespace App\Modules\Transportation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignRunDriverRequest extends FormRequest
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
            'driver_employee_id' => ['required_without:external_driver_id', 'nullable', 'integer', 'prohibits:external_driver_id'],
            'external_driver_id' => ['required_without:driver_employee_id', 'nullable', 'integer'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
