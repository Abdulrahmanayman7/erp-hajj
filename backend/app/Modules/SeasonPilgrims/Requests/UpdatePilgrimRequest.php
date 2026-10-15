<?php

namespace App\Modules\SeasonPilgrims\Requests;

use App\Modules\SeasonPilgrims\Enums\PilgrimGender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePilgrimRequest extends FormRequest
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
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'gender' => ['sometimes', 'nullable', 'string', Rule::in(PilgrimGender::values())],
            'nationality' => ['sometimes', 'nullable', 'string', 'regex:/^[A-Za-z]{2,3}$/'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
