<?php

namespace App\Modules\Transportation\Requests;

use App\Modules\Transportation\Enums\TransportPointType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransportPointRequest extends FormRequest
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
            'code' => [$required, 'string', 'max:40', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'name' => [$required, 'string', 'max:255'],
            'point_type' => [$required, 'string', Rule::in(TransportPointType::values())],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
