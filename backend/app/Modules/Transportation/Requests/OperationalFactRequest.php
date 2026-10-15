<?php

namespace App\Modules\Transportation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Optional time of an operational fact and its reason (ADR-0018 I7).
 */
class OperationalFactRequest extends FormRequest
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
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
