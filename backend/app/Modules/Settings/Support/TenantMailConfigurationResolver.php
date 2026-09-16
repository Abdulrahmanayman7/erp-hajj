<?php

namespace App\Modules\Settings\Support;

use App\Core\Tenancy\Models\Tenant;

/**
 * Central tenant-aware mail resolution: SMTP transport + From identity.
 * Incomplete tenant SMTP never counts as usable — falls back to server config.
 * Non-delivery drivers (log/array) are never treated as deliverable.
 */
final class TenantMailConfigurationResolver
{
    /** @var list<string> */
    public const NON_DELIVERING_MAILERS = ['log', 'array'];

    /** @var list<string> */
    public const ALLOWED_ENCRYPTION = ['tls', 'ssl', 'none'];

    public function __construct(
        private readonly TenantMailSenderResolver $senderResolver,
    ) {}

    public function resolve(?Tenant $tenant): ResolvedMailConfiguration
    {
        $sender = $this->senderResolver->resolve($tenant);

        if ($tenant !== null && $this->isTenantSmtpComplete($tenant)) {
            $encryption = $this->normalizeEncryption((string) $tenant->mail_encryption);
            $scheme = $encryption === 'ssl' ? 'smtps' : 'smtp';

            $username = trim((string) ($tenant->mail_username ?? ''));
            $password = $tenant->mail_password;

            return new ResolvedMailConfiguration(
                source: ResolvedMailConfiguration::SOURCE_TENANT,
                status: ResolvedMailConfiguration::STATUS_TENANT_SMTP,
                deliverable: true,
                fromAddress: $sender['address'],
                fromName: $sender['name'],
                smtpTransport: [
                    'transport' => 'smtp',
                    'scheme' => $scheme,
                    'host' => trim((string) $tenant->mail_host),
                    'port' => (int) $tenant->mail_port,
                    'username' => $username === '' ? null : $username,
                    'password' => is_string($password) && $password !== '' ? $password : null,
                ],
            );
        }

        $serverMailer = (string) config('mail.default', 'log');
        $serverDeliverable = $this->isDeliverableMailer($serverMailer);

        return new ResolvedMailConfiguration(
            source: ResolvedMailConfiguration::SOURCE_SERVER,
            status: $serverDeliverable
                ? ResolvedMailConfiguration::STATUS_SERVER_FALLBACK
                : ResolvedMailConfiguration::STATUS_UNAVAILABLE,
            deliverable: $serverDeliverable,
            fromAddress: $sender['address'],
            fromName: $sender['name'],
            serverMailerName: $serverMailer,
        );
    }

    public function resolveForTenantId(?int $tenantId): ResolvedMailConfiguration
    {
        if ($tenantId === null) {
            return $this->resolve(null);
        }

        $tenant = Tenant::query()->find($tenantId);

        return $this->resolve($tenant);
    }

    public function isDeliverableMailer(?string $mailer): bool
    {
        if ($mailer === null || $mailer === '') {
            return false;
        }

        return ! in_array($mailer, self::NON_DELIVERING_MAILERS, true);
    }

    public function isTenantSmtpComplete(Tenant $tenant): bool
    {
        $mailer = strtolower(trim((string) ($tenant->mail_mailer ?? '')));
        if ($mailer !== 'smtp') {
            return false;
        }

        $host = trim((string) ($tenant->mail_host ?? ''));
        if ($host === '') {
            return false;
        }

        $port = $tenant->mail_port;
        if (! is_numeric($port) || (int) $port < 1 || (int) $port > 65535) {
            return false;
        }

        $encryption = $this->normalizeEncryption((string) ($tenant->mail_encryption ?? ''));
        if ($encryption === null) {
            return false;
        }

        $username = trim((string) ($tenant->mail_username ?? ''));
        $password = $tenant->mail_password;
        if ($username !== '' && (! is_string($password) || $password === '')) {
            return false;
        }

        return true;
    }

    public function normalizeEncryption(string $value): ?string
    {
        $value = strtolower(trim($value));
        if ($value === '' || $value === 'null') {
            return null;
        }

        if ($value === 'starttls') {
            $value = 'tls';
        }

        return in_array($value, self::ALLOWED_ENCRYPTION, true) ? $value : null;
    }
}
