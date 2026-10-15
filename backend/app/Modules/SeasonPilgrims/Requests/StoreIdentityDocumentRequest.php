<?php

namespace App\Modules\SeasonPilgrims\Requests;

use App\Modules\SeasonPilgrims\Enums\IdentityDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIdentityDocumentRequest extends FormRequest
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
            'document_type' => ['required', 'string', Rule::in(IdentityDocumentType::values())],
            'issuing_country' => ['required', 'string', 'regex:/^[A-Za-z]{2,3}$/'],
            'document_number' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9\s\-]+$/'],
        ];
    }
}
