<?php

namespace App\Modules\Settings\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Settings\Exceptions\SettingsDomainException;
use App\Modules\Settings\Support\TenantSettingsResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateTenantSettings
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantSettingsResolver $resolver,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{
     *   general?: array{name?: string, contact_name?: ?string, contact_email?: ?string, contact_phone?: ?string},
     *   regional?: array{timezone?: string},
     *   technical?: array{mail_from_address?: ?string, mail_from_name?: ?string}
     * }  $payload
     * @return array{
     *   general: array{name: string, contact_name: ?string, contact_email: ?string, contact_phone: ?string},
     *   regional: array{timezone: string, locale: string, locale_editable: false},
     *   technical: array{mail_from_address: ?string, mail_from_name: ?string}
     * }
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
                foreach (['mail_from_address', 'mail_from_name'] as $field) {
                    if (! array_key_exists($field, $payload['technical'])) {
                        continue;
                    }
                    $newValue = $payload['technical'][$field];
                    $oldValue = $beforeSnapshot[$field];
                    if ($this->normalizedEquals($oldValue, $newValue)) {
                        continue;
                    }
                    $locked->{$field} = is_string($newValue) ? trim($newValue) : $newValue;
                    if ($locked->{$field} === '') {
                        $locked->{$field} = null;
                    }
                    $changes[$field] = ['before' => $oldValue, 'after' => $locked->{$field}];
                }
            }

            if ($changes === []) {
                // True no-op: success without audit.
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

            // Keep in-memory TenantContext aligned with persisted row.
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
