<?php

namespace App\Modules\Meetings\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AgendaItemRequest extends FormRequest
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
        $isUpdate = in_array($this->method(), ['PATCH', 'PUT'], true);

        return [
            'title' => $isUpdate
                ? ['sometimes', 'required', 'string', 'max:200']
                : ['required', 'string', 'max:200'],
            'description' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'string', 'max:10000'],
            'sort_order' => [$isUpdate ? 'sometimes' : 'nullable', 'nullable', 'integer', 'min:0'],
        ];
    }
}
