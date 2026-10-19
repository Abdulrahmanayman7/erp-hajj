<?php

namespace App\Modules\Accommodation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccommodationPositionRequest extends FormRequest
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
            'code' => [$required, 'string', 'max:40'],
            'tenant_id' => ['prohibited'],
            'season_id' => ['prohibited'],
            'unit_id' => ['prohibited'],
        ];
    }
}
