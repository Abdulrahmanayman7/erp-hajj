<?php

namespace App\Modules\Accommodation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccommodationCancelAssignmentRequest extends FormRequest
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
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'tenant_id' => ['prohibited'],
            'season_id' => ['prohibited'],
            'booking_status' => ['prohibited'],
        ];
    }
}
