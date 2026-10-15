<?php

namespace App\Modules\Transportation\Requests;

use App\Modules\Transportation\Enums\ServiceLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSeasonVehicleRequest extends FormRequest
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
        $creating = $this->isMethod('POST');
        $required = $creating ? 'required' : 'sometimes';

        $rules = [
            'operational_capacity' => [$required, 'integer', 'min:1', 'max:1000'],
            'service_level' => [$required, 'string', Rule::in(ServiceLevel::values())],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];

        if ($creating) {
            $rules['transport_vehicle_id'] = ['required', 'integer'];
        }

        return $rules;
    }
}
