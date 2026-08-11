<?php

namespace App\Modules\Meetings\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MeetingCommentRequest extends FormRequest
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
            'comment' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
