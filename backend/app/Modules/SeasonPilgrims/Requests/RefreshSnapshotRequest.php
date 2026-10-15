<?php

namespace App\Modules\SeasonPilgrims\Requests;

use App\Core\Tenancy\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

class RefreshSnapshotRequest extends FormRequest
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
            'pilgrim_identity_document_id' => ['nullable', 'integer', new TenantExists('pilgrim_identity_documents')],
        ];
    }
}
