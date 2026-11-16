<?php

namespace App\Modules\Gps\Jobs;

use App\Core\Tenancy\Jobs\TenantAware;
use App\Modules\Gps\Models\GpsProvider;
use App\Modules\Gps\Support\SyncGpsProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SyncGpsProviderJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use TenantAware;

    public function __construct(
        public int $gpsProviderId,
    ) {
        $this->captureTenantContext();
    }

    public function handle(SyncGpsProvider $sync): void
    {
        $provider = GpsProvider::query()->whereKey($this->gpsProviderId)->first();
        if ($provider === null || ! $provider->is_enabled) {
            return;
        }

        $sync->sync($provider);
    }
}
