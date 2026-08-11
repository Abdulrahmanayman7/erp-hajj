<?php

namespace App\Modules\Documents\Requests;

use App\Modules\Documents\Enums\DocumentLinkableType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadDocumentRequest extends FormRequest
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
            'file' => ['required', 'file'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer'],
            'linkable_type' => ['nullable', 'string', Rule::in(DocumentLinkableType::mvpAliases())],
            'linkable_id' => ['nullable', 'integer'],
        ];
    }
}
