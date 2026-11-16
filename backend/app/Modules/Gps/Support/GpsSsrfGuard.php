<?php

namespace App\Modules\Gps\Support;

use App\Modules\Gps\Exceptions\GpsLiveOutboundBlockedException;

/**
 * Restrict outbound GPS HTTP to https URLs with non-private hosts (SSRF protection).
 */
final class GpsSsrfGuard
{
    public function assertSafeHttpsUrl(string $url): string
    {
        $trimmed = trim($url);
        if ($trimmed === '' || filter_var($trimmed, FILTER_VALIDATE_URL) === false) {
            throw new GpsLiveOutboundBlockedException('Invalid GPS provider URL.');
        }

        $parts = parse_url($trimmed);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme !== 'https') {
            throw new GpsLiveOutboundBlockedException('GPS provider URL must use HTTPS.');
        }

        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local')) {
            throw new GpsLiveOutboundBlockedException('GPS provider host is not allowed.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new GpsLiveOutboundBlockedException('GPS provider host resolves to a private or reserved address.');
            }
        }

        $allowlist = config('gps.outbound_host_allowlist', []);
        if (is_array($allowlist) && $allowlist !== []) {
            $allowed = false;
            foreach ($allowlist as $pattern) {
                $pattern = strtolower(trim((string) $pattern));
                if ($pattern === '') {
                    continue;
                }
                if ($host === $pattern || str_ends_with($host, '.'.$pattern)) {
                    $allowed = true;
                    break;
                }
            }
            if (! $allowed) {
                throw new GpsLiveOutboundBlockedException('GPS provider host is not on the outbound allowlist.');
            }
        }

        return $trimmed;
    }
}
