<?php

namespace App\Modules\Accommodation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccommodationOpsOccurredAtRequest extends FormRequest
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
            'occurred_at' => ['sometimes', 'nullable', 'date'],
            'tenant_id' => ['prohibited'],
            'season_id' => ['prohibited'],
            'booking_status' => ['prohibited'],
            'operational_status' => ['prohibited'],
        ];
    }
}
