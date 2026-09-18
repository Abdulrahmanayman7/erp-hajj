<?php

namespace App\Modules\Meetings\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MinutesRequest extends FormRequest
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
            'minutes_body' => ['required', 'string', 'max:100000'],
        ];
    }
}
