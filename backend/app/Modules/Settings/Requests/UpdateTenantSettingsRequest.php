<?php

namespace App\Modules\Settings\Requests;

use App\Modules\Settings\Exceptions\SettingsDomainException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTenantSettingsRequest extends FormRequest
{
    /** @var list<string> */
    private const ROOT_ALLOWED = ['general', 'regional', 'technical'];

    /** @var list<string> */
    private const GENERAL_ALLOWED = ['name', 'contact_name', 'contact_email', 'contact_phone'];

    /** @var list<string> */
    private const REGIONAL_ALLOWED = ['timezone'];

    /** @var list<string> */
    private const TECHNICAL_ALLOWED = ['mail_from_address', 'mail_from_name'];

    /** @var list<string> */
    private const IMMUTABLE_ROOT = [
        'tenant_id',
        'tenant_code',
        'status',
        'notes',
        'locale',
        'suspended_at',
        'archived_at',
        'id',
    ];

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
            'general' => ['sometimes', 'array'],
            'general.name' => ['sometimes', 'required', 'string', 'max:255'],
            'general.contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'general.contact_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'general.contact_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'regional' => ['sometimes', 'array'],
            'regional.timezone' => ['sometimes', 'required', 'string', 'max:64'],
            'regional.locale' => ['prohibited'],
            'technical' => ['sometimes', 'array'],
            'technical.mail_from_address' => ['sometimes', 'nullable', 'email', 'max:255'],
            'technical.mail_from_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tenant_id' => ['prohibited'],
            'tenant_code' => ['prohibited'],
            'status' => ['prohibited'],
            'notes' => ['prohibited'],
            'locale' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var array<string, mixed> $payload */
            $payload = $this->all();

            foreach (array_keys($payload) as $key) {
                if (in_array($key, self::IMMUTABLE_ROOT, true)) {
                    throw SettingsDomainException::immutableField();
                }
                if (! in_array($key, self::ROOT_ALLOWED, true)) {
                    throw SettingsDomainException::unknownField();
                }
            }

            if (isset($payload['general']) && is_array($payload['general'])) {
                foreach (array_keys($payload['general']) as $key) {
                    if ($key === 'locale' || $key === 'tenant_code' || $key === 'status' || $key === 'notes') {
                        throw SettingsDomainException::immutableField();
                    }
                    if (! in_array($key, self::GENERAL_ALLOWED, true)) {
                        throw SettingsDomainException::unknownField();
                    }
                }
            }

            if (isset($payload['regional']) && is_array($payload['regional'])) {
                foreach (array_keys($payload['regional']) as $key) {
                    if ($key === 'locale') {
                        throw SettingsDomainException::immutableField();
                    }
                    if (! in_array($key, self::REGIONAL_ALLOWED, true)) {
                        throw SettingsDomainException::unknownField();
                    }
                }
            }

            if (isset($payload['technical']) && is_array($payload['technical'])) {
                foreach (array_keys($payload['technical']) as $key) {
                    if (! in_array($key, self::TECHNICAL_ALLOWED, true)) {
                        throw SettingsDomainException::unknownField();
                    }
                }
            }

            $hasMutable = false;
            if (isset($payload['general']) && is_array($payload['general']) && $payload['general'] !== []) {
                $hasMutable = true;
            }
            if (isset($payload['regional']) && is_array($payload['regional']) && $payload['regional'] !== []) {
                $hasMutable = true;
            }
            if (isset($payload['technical']) && is_array($payload['technical']) && $payload['technical'] !== []) {
                $hasMutable = true;
            }

            if (! $hasMutable) {
                throw SettingsDomainException::noChanges();
            }

            foreach (['name', 'contact_name'] as $textField) {
                if (! array_key_exists($textField, $payload['general'] ?? [])) {
                    continue;
                }
                $value = $payload['general'][$textField] ?? null;
                if (is_string($value) && (str_contains($value, '<') || str_contains($value, '>'))) {
                    $validator->errors()->add("general.{$textField}", 'لا يُسمح بوسوم HTML.');
                }
            }

            if (array_key_exists('mail_from_name', $payload['technical'] ?? [])) {
                $value = $payload['technical']['mail_from_name'] ?? null;
                if (is_string($value) && (str_contains($value, '<') || str_contains($value, '>'))) {
                    $validator->errors()->add('technical.mail_from_name', 'لا يُسمح بوسوم HTML.');
                }
            }

            if (isset($payload['regional']['timezone']) && is_string($payload['regional']['timezone'])) {
                $tz = $payload['regional']['timezone'];
                if (! in_array($tz, timezone_identifiers_list(), true)) {
                    throw SettingsDomainException::invalidTimezone();
                }
            }
        });
    }

    /**
     * @return array{
     *   general?: array{name?: string, contact_name?: ?string, contact_email?: ?string, contact_phone?: ?string},
     *   regional?: array{timezone?: string},
     *   technical?: array{mail_from_address?: ?string, mail_from_name?: ?string}
     * }
     */
    public function settingsPayload(): array
    {
        /** @var array{general?: array<string, mixed>, regional?: array<string, mixed>, technical?: array<string, mixed>} $validated */
        $validated = $this->validated();

        $out = [];
        if (isset($validated['general']) && is_array($validated['general'])) {
            $general = [];
            foreach (self::GENERAL_ALLOWED as $key) {
                if (array_key_exists($key, $validated['general'])) {
                    $general[$key] = $validated['general'][$key];
                }
            }
            if ($general !== []) {
                $out['general'] = $general;
            }
        }
        if (isset($validated['regional']) && is_array($validated['regional'])) {
            $regional = [];
            if (array_key_exists('timezone', $validated['regional'])) {
                $regional['timezone'] = $validated['regional']['timezone'];
            }
            if ($regional !== []) {
                $out['regional'] = $regional;
            }
        }
        if (isset($validated['technical']) && is_array($validated['technical'])) {
            $technical = [];
            foreach (self::TECHNICAL_ALLOWED as $key) {
                if (array_key_exists($key, $validated['technical'])) {
                    $technical[$key] = $validated['technical'][$key];
                }
            }
            if ($technical !== []) {
                $out['technical'] = $technical;
            }
        }

        return $out;
    }
}
