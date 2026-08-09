<?php

namespace App\Modules\Contracts\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContractTransitionRequest extends FormRequest
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
        $method = $this->route()?->getActionMethod();
        $commentRequired = in_array($method, ['returnDraft', 'cancel'], true);

        return [
            'comment' => $commentRequired
                ? ['required', 'string', 'max:5000']
                : ['nullable', 'string', 'max:5000'],
        ];
    }
}
