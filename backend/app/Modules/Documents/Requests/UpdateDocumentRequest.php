<?php

namespace App\Modules\Documents\Requests;

use App\Modules\Documents\Enums\DocumentLinkableType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'filled', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'category_id' => ['sometimes', 'nullable', 'integer'],
            'linkable_type' => ['sometimes', 'nullable', 'string', Rule::in(DocumentLinkableType::mvpAliases())],
            'linkable_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
