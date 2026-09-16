<?php

namespace App\Modules\Settings\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{
 *   general: array{name: string, contact_name: ?string, contact_email: ?string, contact_phone: ?string},
 *   regional: array{timezone: string, locale: string, locale_editable: false},
 *   technical: array{mail_from_address: ?string, mail_from_name: ?string}
 * } $resource
 */
class TenantSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{general: array<string, mixed>, regional: array<string, mixed>, technical: array<string, mixed>} $data */
        $data = $this->resource;

        return [
            'general' => [
                'name' => $data['general']['name'],
                'contact_name' => $data['general']['contact_name'],
                'contact_email' => $data['general']['contact_email'],
                'contact_phone' => $data['general']['contact_phone'],
            ],
            'regional' => [
                'timezone' => $data['regional']['timezone'],
                'locale' => $data['regional']['locale'],
                'locale_editable' => false,
            ],
            'technical' => [
                'mail_from_address' => $data['technical']['mail_from_address'],
                'mail_from_name' => $data['technical']['mail_from_name'],
            ],
        ];
    }
}
