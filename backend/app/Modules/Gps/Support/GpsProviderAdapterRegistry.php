<?php

namespace App\Modules\Gps\Support;

use App\Modules\Gps\Contracts\GpsProviderAdapterInterface;
use App\Modules\Gps\Enums\GpsConnectionMethod;
use App\Modules\Gps\Providers\Adapters\MobileDriverGpsAdapter;
use App\Modules\Gps\Providers\Adapters\RestPullGpsAdapter;
use App\Modules\Gps\Providers\Adapters\SimulatorGpsAdapter;
use App\Modules\Gps\Providers\Adapters\TraccarGpsAdapter;
use App\Modules\Gps\Providers\Adapters\WebhookGpsAdapter;
use InvalidArgumentException;

final class GpsProviderAdapterRegistry
{
    public function __construct(
        private readonly RestPullGpsAdapter $restPull,
        private readonly WebhookGpsAdapter $webhook,
        private readonly TraccarGpsAdapter $traccar,
        private readonly MobileDriverGpsAdapter $mobileDriver,
        private readonly SimulatorGpsAdapter $simulator,
    ) {}

    public function resolve(string $connectionMethod): GpsProviderAdapterInterface
    {
        $method = GpsConnectionMethod::tryFrom($connectionMethod);
        if ($method === null) {
            throw new InvalidArgumentException('Unsupported GPS connection method.');
        }

        return match ($method) {
            GpsConnectionMethod::RestPull => $this->restPull,
            GpsConnectionMethod::Webhook => $this->webhook,
            GpsConnectionMethod::Traccar => $this->traccar,
            GpsConnectionMethod::MobileDriver => $this->mobileDriver,
            GpsConnectionMethod::Simulator => $this->simulator,
        };
    }

    /**
     * @return list<string>
     */
    public function supportedMethods(): array
    {
        return GpsConnectionMethod::values();
    }
}
