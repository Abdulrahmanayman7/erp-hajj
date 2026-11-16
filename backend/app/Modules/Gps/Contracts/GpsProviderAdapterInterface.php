<?php

namespace App\Modules\Gps\Contracts;

use App\Modules\Gps\Models\GpsProvider;
use App\Modules\Gps\Support\GpsConnectionTestResult;

/**
 * Adapter contract for a configurable GPS connection method.
 */
interface GpsProviderAdapterInterface
{
    public function connectionMethod(): string;

    public function testConnection(GpsProvider $provider): GpsConnectionTestResult;

    /**
     * Pull latest positions for bound devices. Empty when the method is push-only
     * or when outbound live pulls are gated (G4).
     *
     * @return list<NormalizedLocationDto>
     */
    public function fetchLatestPositions(GpsProvider $provider): array;
}
