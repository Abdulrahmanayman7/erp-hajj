<?php

namespace App\Modules\SeasonPilgrims\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSeasonProgramRequest extends FormRequest
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
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
