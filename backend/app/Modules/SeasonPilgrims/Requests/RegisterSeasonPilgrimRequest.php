<?php

namespace App\Modules\SeasonPilgrims\Requests;

use App\Core\Tenancy\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The season comes from the Season Context, never from the payload.
 * Program/group ids are resolved inside the context season by the action.
 */
class RegisterSeasonPilgrimRequest extends FormRequest
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
            'pilgrim_id' => ['required', 'integer', new TenantExists('pilgrims')],
            'pilgrim_identity_document_id' => ['nullable', 'integer', new TenantExists('pilgrim_identity_documents')],
            'season_program_id' => ['nullable', 'integer'],
            'season_pilgrim_group_id' => ['nullable', 'integer'],
        ];
    }
}
