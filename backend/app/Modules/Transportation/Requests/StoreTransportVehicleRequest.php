<?php

namespace App\Modules\Transportation\Requests;

use App\Modules\Transportation\Enums\VehicleClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransportVehicleRequest extends FormRequest
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
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'plate_number' => [$required, 'string', 'max:32', 'regex:/^[\pL\pN \-]+$/u'],
            'vehicle_class' => [$required, 'string', Rule::in(VehicleClass::values())],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
