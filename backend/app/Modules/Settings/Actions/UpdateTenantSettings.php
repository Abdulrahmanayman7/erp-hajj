<?php

namespace App\Modules\Settings\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Settings\Exceptions\SettingsDomainException;
use App\Modules\Settings\Support\TenantMailConfigurationResolver;
use App\Modules\Settings\Support\TenantSettingsResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateTenantSettings
{
    /** @var list<string> */
    private const TECHNICAL_SCALAR_FIELDS = [
        'mail_mailer',
        'mail_host',
        'mail_port',
        'mail_encryption',
        'mail_username',
        'mail_from_address',
        'mail_from_name',
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantSettingsResolver $resolver,
        private readonly TenantMailConfigurationResolver $mailResolver,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{
     *   general?: array{name?: string, contact_name?: ?string, contact_email?: ?string, contact_phone?: ?string},
     *   regional?: array{timezone?: string},
     *   technical?: array<string, mixed>
     * }  $payload
     * @return array<string, mixed>
     */
    public function execute(User $actor, array $payload, Request $request): array
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $payload, $request, $tenant): array {
            /** @var Tenant $locked */
            $locked = Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();

            $beforeSnapshot = [
                'name' => $locked->name,
                'contact_name' => $locked->contact_name,
                'contact_email' => $locked->contact_email,
                'contact_phone' => $locked->contact_phone,
                'timezone' => $locked->timezone,
                'mail_from_address' => $locked->mail_from_address,
                'mail_from_name' => $locked->mail_from_name,
                'mail_mailer' => $locked->mail_mailer,
                'mail_host' => $locked->mail_host,
                'mail_port' => $locked->mail_port,
                'mail_encryption' => $locked->mail_encryption,
                'mail_username' => $locked->mail_username,
                'mail_password_configured' => is_string($locked->mail_password) && $locked->mail_password !== '',
            ];

            $changes = [];

            if (isset($payload['general'])) {
                foreach (['name', 'contact_name', 'contact_email', 'contact_phone'] as $field) {
                    if (! array_key_exists($field, $payload['general'])) {
                        continue;
                    }
                    $newValue = $payload['general'][$field];
                    $oldValue = $beforeSnapshot[$field];
                    if ($this->normalizedEquals($oldValue, $newValue)) {
                        continue;
                    }
                    if ($field === 'name') {
                        $name = is_string($newValue) ? trim($newValue) : '';
                        if ($name === '') {
                            throw SettingsDomainException::noChanges();
                        }
                        $taken = Tenant::query()
                            ->where('name', $name)
                            ->where('id', '!=', $locked->id)
                            ->exists();
                        if ($taken) {
                            throw SettingsDomainException::nameTaken();
                        }
                        $locked->name = $name;
                        $changes['name'] = ['before' => $oldValue, 'after' => $name];
                    } else {
                        $locked->{$field} = is_string($newValue) ? trim($newValue) : $newValue;
                        if ($locked->{$field} === '') {
                            $locked->{$field} = null;
                        }
                        $changes[$field] = ['before' => $oldValue, 'after' => $locked->{$field}];
                    }
                }
            }

            if (isset($payload['regional']['timezone'])) {
                $timezone = (string) $payload['regional']['timezone'];
                if (! in_array($timezone, timezone_identifiers_list(), true)) {
                    throw SettingsDomainException::invalidTimezone();
                }
                if ($timezone !== (string) $beforeSnapshot['timezone']) {
                    $locked->timezone = $timezone;
                    $changes['timezone'] = ['before' => $beforeSnapshot['timezone'], 'after' => $timezone];
                }
            }

            if (isset($payload['technical'])) {
                $technical = $payload['technical'];

                foreach (self::TECHNICAL_SCALAR_FIELDS as $field) {
                    if (! array_key_exists($field, $technical)) {
                        continue;
                    }

                    $newValue = $technical[$field];
                    $oldValue = $beforeSnapshot[$field];

                    if ($field === 'mail_port') {
                        $normalizedPort = $newValue === null || $newValue === ''
                            ? null
                            : (int) $newValue;
                        if ((int) ($oldValue ?? 0) === (int) ($normalizedPort ?? 0)
                            && ($oldValue === null) === ($normalizedPort === null)) {
                            if ($oldValue === null && $normalizedPort === null) {
                                continue;
                            }
                            if ($oldValue !== null && $normalizedPort !== null && (int) $oldValue === $normalizedPort) {
                                continue;
                            }
                        }
                        $locked->mail_port = $normalizedPort;
                        $changes['mail_port'] = ['before' => $oldValue, 'after' => $normalizedPort];

                        continue;
                    }

                    if ($field === 'mail_encryption' && is_string($newValue)) {
                        $newValue = $this->mailResolver->normalizeEncryption($newValue) ?? trim($newValue);
                    }

                    if ($field === 'mail_mailer' && is_string($newValue)) {
                        $newValue = strtolower(trim($newValue));
                        if ($newValue === '') {
                            $newValue = null;
                        }
                    }

                    if ($this->normalizedEquals($oldValue, $newValue)) {
                        continue;
                    }

                    $locked->{$field} = is_string($newValue) ? trim($newValue) : $newValue;
                    if ($locked->{$field} === '') {
                        $locked->{$field} = null;
                    }
                    $changes[$field] = ['before' => $oldValue, 'after' => $locked->{$field}];
                }

                $clearPassword = (bool) ($technical['mail_password_clear'] ?? false);
                $newPassword = $technical['mail_password'] ?? null;
                $hasNewPassword = is_string($newPassword) && trim($newPassword) !== '';

                if ($clearPassword && ! $hasNewPassword) {
                    if ($beforeSnapshot['mail_password_configured']) {
                        $locked->mail_password = null;
                        $changes['mail_auth_updated'] = ['before' => true, 'after' => false];
                    }
                } elseif ($hasNewPassword) {
                    $locked->mail_password = trim($newPassword);
                    $changes['mail_auth_updated'] = [
                        'before' => $beforeSnapshot['mail_password_configured'],
                        'after' => true,
                    ];
                }
                // Blank / omitted password → keep existing encrypted value.
            }

            if ($changes === []) {
                $this->tenantContext->set($locked->fresh() ?? $locked);

                return $this->resolver->resolve($locked);
            }

            $locked->save();

            $beforeValues = [];
            $afterValues = [];
            foreach ($changes as $field => $pair) {
                $beforeValues[$field] = $pair['before'];
                $afterValues[$field] = $pair['after'];
            }

            $this->security->record(AuthorizationSecurityEvent::TENANT_SETTINGS_UPDATED, [
                'tenant_id' => $locked->id,
                'actor_id' => $actor->id,
                'entity_type' => 'tenant',
                'entity_id' => $locked->id,
                'entity_number' => $locked->tenant_code,
                'entity_label' => $locked->name,
                'before_values' => $beforeValues,
                'after_values' => $afterValues,
            ], $request);

            $fresh = $locked->fresh() ?? $locked;
            $this->tenantContext->clear();
            $this->tenantContext->set($fresh);

            return $this->resolver->resolve($fresh);
        });
    }

    private function normalizedEquals(mixed $old, mixed $new): bool
    {
        $normalize = static function (mixed $value): ?string {
            if ($value === null) {
                return null;
            }
            if (! is_string($value) && ! is_numeric($value)) {
                return null;
            }
            $trimmed = trim((string) $value);

            return $trimmed === '' ? null : $trimmed;
        };

        return $normalize($old) === $normalize($new);
    }
}
