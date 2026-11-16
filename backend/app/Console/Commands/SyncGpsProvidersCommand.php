<?php

namespace App\Console\Commands;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Modules\Gps\Enums\GpsConnectionMethod;
use App\Modules\Gps\Jobs\SyncGpsProviderJob;
use App\Modules\Gps\Models\GpsProvider;
use Illuminate\Console\Command;

final class SyncGpsProvidersCommand extends Command
{
    protected $signature = 'gps:sync-providers';

    protected $description = 'Enqueue pull/sync for enabled REST/Traccar/Simulator GPS providers (G4 live still gated).';

    public function handle(TenantContext $tenantContext): int
    {
        $methods = [
            GpsConnectionMethod::RestPull->value,
            GpsConnectionMethod::Traccar->value,
            GpsConnectionMethod::Simulator->value,
        ];

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) use ($tenantContext, $methods): void {
            $tenantContext->runAsTenant($tenant, function () use ($methods): void {
                GpsProvider::query()
                    ->where('is_enabled', true)
                    ->whereIn('connection_method', $methods)
                    ->orderBy('id')
                    ->each(function (GpsProvider $provider): void {
                        SyncGpsProviderJob::dispatch($provider->id);
                    });
            });
        });

        $this->info('GPS provider sync jobs dispatched.');

        return self::SUCCESS;
    }
}
