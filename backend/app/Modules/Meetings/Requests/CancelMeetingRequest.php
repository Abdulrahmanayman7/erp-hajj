<?php

namespace App\Modules\Meetings\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelMeetingRequest extends FormRequest
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
            'comment' => ['required', 'string', 'max:5000'],
        ];
    }
}
