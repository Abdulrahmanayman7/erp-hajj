<?php

namespace App\Modules\Settings\Support;

/**
 * Immutable resolved outbound mail configuration for one send operation.
 *
 * @phpstan-type TransportArray array{
 *   transport: string,
 *   scheme: ?string,
 *   host: string,
 *   port: int,
 *   username: ?string,
 *   password: ?string,
 * }
 */
final class ResolvedMailConfiguration
{
    public const SOURCE_TENANT = 'tenant';

    public const SOURCE_SERVER = 'server';

    public const STATUS_TENANT_SMTP = 'tenant_smtp';

    public const STATUS_SERVER_FALLBACK = 'server_fallback';

    public const STATUS_UNAVAILABLE = 'unavailable';

    /**
     * @param  TransportArray|null  $smtpTransport  Present only when source is tenant SMTP.
     */
    public function __construct(
        public readonly string $source,
        public readonly string $status,
        public readonly bool $deliverable,
        public readonly string $fromAddress,
        public readonly string $fromName,
        public readonly ?array $smtpTransport = null,
        public readonly ?string $serverMailerName = null,
    ) {}

    public function usesTenantSmtp(): bool
    {
        return $this->source === self::SOURCE_TENANT && $this->smtpTransport !== null;
    }
}
