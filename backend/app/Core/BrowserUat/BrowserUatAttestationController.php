<?php

namespace App\Core\BrowserUat;

use App\Core\Shared\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Returns the live Laravel connection database name for Browser UAT gates.
 *
 * Disabled unless APP_ENV is local|testing AND BROWSER_UAT_ATTESTATION_ENABLED=true.
 * Never returns credentials, hosts, or connection strings.
 */
final class BrowserUatAttestationController
{
    public function __invoke(): JsonResponse
    {
        if (! $this->isAttestationAllowed()) {
            // Fail closed — indistinguishable from a missing route in production.
            return ApiResponse::error(
                message: 'غير موجود',
                code: 'BROWSER_UAT_ATTESTATION_UNAVAILABLE',
                status: 404,
            );
        }

        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $database = $this->resolveDatabaseName($driver);

        return ApiResponse::success(
            data: [
                'attestation' => 'browser_uat',
                'app_env' => (string) config('app.env'),
                'driver' => $driver,
                'database' => $database,
            ],
            message: 'Browser UAT attestation',
        );
    }

    private function isAttestationAllowed(): bool
    {
        if (! (bool) config('browser_uat.attestation_enabled', false)) {
            return false;
        }

        $env = (string) config('app.env');
        if ($env === 'production') {
            return false;
        }

        $allowed = config('browser_uat.allowed_app_envs', ['local', 'testing']);
        if (! is_array($allowed) || ! in_array($env, $allowed, true)) {
            return false;
        }

        return true;
    }

    private function resolveDatabaseName(string $driver): string
    {
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $row = DB::selectOne('select database() as db');

            return is_object($row) ? (string) ($row->db ?? '') : '';
        }

        return (string) DB::connection()->getDatabaseName();
    }
}
