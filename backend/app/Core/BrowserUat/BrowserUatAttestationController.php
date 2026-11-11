<?php

namespace App\Core\BrowserUat;

use App\Core\Shared\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Returns the live Laravel connection database name for Browser UAT gates.
 *
 * Requires ALL of:
 * - BROWSER_UAT_ATTESTATION_ENABLED=true
 * - APP_ENV in {local, testing} (production/staging always denied)
 * - Non-empty configured token
 * - Request header X-Browser-Uat-Token matching the configured token (timing-safe)
 *
 * Never returns credentials, hosts, connection strings, or the token itself.
 */
final class BrowserUatAttestationController
{
    public const TOKEN_HEADER = 'X-Browser-Uat-Token';

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->isAttestationAllowed($request)) {
            // Fail closed — same shape for disabled / wrong env / missing/invalid token.
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
        )->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    }

    private function isAttestationAllowed(Request $request): bool
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

        $expected = (string) config('browser_uat.attestation_token', '');
        if ($expected === '') {
            return false;
        }

        $provided = (string) $request->header(self::TOKEN_HEADER, '');
        if ($provided === '') {
            return false;
        }

        return hash_equals($expected, $provided);
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
