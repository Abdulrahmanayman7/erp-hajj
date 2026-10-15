<?php

namespace App\Modules\Transportation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExternalDriverRequest extends FormRequest
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
            'full_name' => [$required, 'string', 'max:150'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
