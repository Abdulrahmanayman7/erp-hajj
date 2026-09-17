<?php

namespace App\Modules\Platform\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferTenantOwnershipRequest extends FormRequest
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
            'new_owner_id' => ['required', 'integer'],
        ];
    }

    public function newOwnerId(): int
    {
        return (int) $this->validated('new_owner_id');
    }
}
