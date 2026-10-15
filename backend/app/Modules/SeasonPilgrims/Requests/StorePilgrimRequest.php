<?php

namespace App\Modules\SeasonPilgrims\Requests;

use App\Modules\SeasonPilgrims\Enums\PilgrimGender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePilgrimRequest extends FormRequest
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
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', 'string', Rule::in(PilgrimGender::values())],
            'nationality' => ['nullable', 'string', 'regex:/^[A-Za-z]{2,3}$/'],
            'phone' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
