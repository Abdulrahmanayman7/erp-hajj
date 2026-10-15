<?php

namespace App\Modules\SeasonPilgrims\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mandatory reason for exceptional/sensitive transitions (season reopen,
 * season pilgrim cancellation).
 */
class ReasonRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}
