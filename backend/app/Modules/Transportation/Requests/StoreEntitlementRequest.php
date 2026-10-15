<?php

namespace App\Modules\Transportation\Requests;

use App\Modules\Transportation\Enums\ServiceLevel;
use App\Modules\Transportation\Enums\VehicleClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEntitlementRequest extends FormRequest
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

        $rules = [
            'vehicle_class' => ['sometimes', 'nullable', 'string', Rule::in(VehicleClass::values())],
            'service_level' => ['sometimes', 'nullable', 'string', Rule::in(ServiceLevel::values())],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];

        if ($creating) {
            $rules['season_program_id'] = ['required', 'integer'];
        }

        return $rules;
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->isMethod('POST')) {
                    return;
                }

                if ($this->input('vehicle_class') === null && $this->input('service_level') === null) {
                    $validator->errors()->add('vehicle_class', 'حدد فئة المركبة أو مستوى الخدمة على الأقل.');
                }
            },
        ];
    }
}
