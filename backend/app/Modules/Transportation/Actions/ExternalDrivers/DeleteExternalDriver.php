<?php

namespace App\Modules\Transportation\Actions\ExternalDrivers;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportExternalDriver;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Hard delete only while no SeasonVehicle default or Run assignment
 * references the driver.
 */
final class DeleteExternalDriver
{
    public function __construct(private readonly TransportAudit $audit) {}

    public function execute(User $actor, TransportExternalDriver $driver, Request $request): void
    {
        DB::transaction(function () use ($actor, $driver, $request): void {
            /** @var TransportExternalDriver $locked */
            $locked = TransportExternalDriver::query()->whereKey($driver->id)->lockForUpdate()->firstOrFail();

            if ($locked->isUsed()) {
                throw TransportationDomainException::externalDriverInUse();
            }

            $locked->delete();

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_EXTERNAL_DRIVER_DELETED, $actor->id, 'transport_external_driver', $locked->id, null, $locked->full_name, [], $request);
        });
    }
}
