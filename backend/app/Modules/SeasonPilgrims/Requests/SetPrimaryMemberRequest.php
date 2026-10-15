<?php

namespace App\Modules\SeasonPilgrims\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetPrimaryMemberRequest extends FormRequest
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
            'season_pilgrim_id' => ['required', 'integer'],
        ];
    }
}
