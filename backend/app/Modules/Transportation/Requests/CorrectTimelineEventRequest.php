<?php

namespace App\Modules\Transportation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CorrectTimelineEventRequest extends FormRequest
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
            'occurred_at' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}
