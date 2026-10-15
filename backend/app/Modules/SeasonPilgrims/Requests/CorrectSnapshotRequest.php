<?php

namespace App\Modules\SeasonPilgrims\Requests;

use App\Modules\SeasonPilgrims\Enums\IdentityDocumentType;
use App\Modules\SeasonPilgrims\Enums\PilgrimGender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CorrectSnapshotRequest extends FormRequest
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
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'gender' => ['sometimes', 'nullable', 'string', Rule::in(PilgrimGender::values())],
            'nationality' => ['sometimes', 'nullable', 'string', 'regex:/^[A-Za-z]{2,3}$/'],
            'document_type' => ['sometimes', 'nullable', 'string', Rule::in(IdentityDocumentType::values())],
            'document_issuing_country' => ['sometimes', 'nullable', 'string', 'regex:/^[A-Za-z]{2,3}$/'],
            'document_number' => ['sometimes', 'nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9\s\-]+$/'],
        ];
    }
}
