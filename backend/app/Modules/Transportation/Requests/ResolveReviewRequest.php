<?php

namespace App\Modules\Transportation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResolveReviewRequest extends FormRequest
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
            'resolution_note' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}
