<?php

namespace App\Console\Commands;

use App\Modules\Gps\Contracts\GpsLocationHistoryWriterInterface;
use App\Modules\Gps\Contracts\GpsProviderInterface;
use App\Modules\Gps\Providers\NullGpsProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Non-production readiness check for G4 Live GPS outbound.
 *
 * Never authorizes a live vendor. Refuses APP_ENV=production.
 */
final class VerifyGpsG4ReadinessCommand extends Command
{
    protected $signature = 'gps:verify-g4-readiness';

    protected $description = 'Verify G4 GPS live-outbound readiness without authorizing a vendor or production activation';

    public function handle(): int
    {
        $env = (string) config('app.env');
        if ($env === 'production') {
            $this->error('REFUSED: GPS G4 readiness verification is forbidden in production.');

            return self::FAILURE;
        }

        $providerKey = (string) config('gps.provider', 'null');
        $liveAuthorized = (bool) config('gps.live_outbound_authorized', false);
        $liveProd = (bool) config('gps.live_outbound_authorized_in_production', false);
        $simulator = (bool) config('gps.simulator_enabled', false);
        $allowlist = config('gps.outbound_host_allowlist', []);
        $historyEnabled = (bool) config('gps.history.enabled', false);
        $retentionDays = config('gps.history.retention_days');

        $this->line('APP_ENV='.$env);
        $this->line('GPS_PROVIDER='.$providerKey);
        $this->line('GPS_LIVE_OUTBOUND_AUTHORIZED='.($liveAuthorized ? 'true' : 'false'));
        $this->line('GPS_LIVE_OUTBOUND_AUTHORIZED_IN_PRODUCTION='.($liveProd ? 'true' : 'false'));
        $this->line('GPS_SIMULATOR_ENABLED='.($simulator ? 'true' : 'false'));
        $this->line('GPS_OUTBOUND_HOST_ALLOWLIST_COUNT='.(is_array($allowlist) ? (string) count($allowlist) : '0'));
        $this->line('gps.history.enabled='.($historyEnabled ? 'true' : 'false'));
        $this->line('gps.history.retention_days='.(is_numeric($retentionDays) ? (string) $retentionDays : '[unset/TBD]'));
        $this->line('GpsProviderInterface='.(app(GpsProviderInterface::class) instanceof NullGpsProvider ? 'NullGpsProvider' : 'other'));
        $this->line('GpsLocationHistoryWriterInterface='.(app()->bound(GpsLocationHistoryWriterInterface::class) ? 'BOUND' : 'unbound'));
        $this->line('gps_vehicle_location_history='.(Schema::hasTable('gps_vehicle_location_history') ? 'PRESENT' : 'absent'));

        $checks = [
            'default_provider_is_null' => $providerKey === 'null',
            'live_outbound_default_closed' => $liveAuthorized === false,
            'production_live_flag_closed' => $liveProd === false,
            'history_disabled' => $historyEnabled === false,
            'retention_unset' => $retentionDays === null || $retentionDays === '',
            'history_writer_unbound' => ! app()->bound(GpsLocationHistoryWriterInterface::class),
            'history_table_absent' => ! Schema::hasTable('gps_vehicle_location_history'),
        ];

        foreach ($checks as $name => $ok) {
            $this->line($name.'='.($ok ? 'PASS' : 'FAIL'));
        }

        if (in_array(false, $checks, true)) {
            $this->error('G4/G5 readiness posture drifted. Investigate before any Owner activation request.');

            return self::FAILURE;
        }

        $this->info('G4 readiness check complete. Live vendor binding NOT authorized by this command.');
        $this->comment('Next Owner gate: named vendor/API contract + credentials vault + disposable UAT proof + written authorization.');

        return self::SUCCESS;
    }
}
