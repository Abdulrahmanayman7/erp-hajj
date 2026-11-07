<?php

namespace App\Modules\RisksAndComplaints\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseComplaintRequest extends FormRequest
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
            'close_reason' => ['required', 'string'],
        ];
    }
}
