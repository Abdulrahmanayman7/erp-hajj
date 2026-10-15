<?php

namespace App\Modules\Transportation\Actions\ExternalDrivers;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Transportation\Models\TransportExternalDriver;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SetExternalDriverActive
{
    public function __construct(private readonly TransportAudit $audit) {}

    public function execute(User $actor, TransportExternalDriver $driver, bool $active, Request $request): TransportExternalDriver
    {
        return DB::transaction(function () use ($actor, $driver, $active, $request): TransportExternalDriver {
            /** @var TransportExternalDriver $locked */
            $locked = TransportExternalDriver::query()->whereKey($driver->id)->lockForUpdate()->firstOrFail();

            if ($locked->is_active !== $active) {
                $locked->update(['is_active' => $active]);

                $this->audit->record(
                    $active ? AuthorizationSecurityEvent::TRANSPORT_EXTERNAL_DRIVER_ACTIVATED : AuthorizationSecurityEvent::TRANSPORT_EXTERNAL_DRIVER_DEACTIVATED,
                    $actor->id, 'transport_external_driver', $locked->id, null, $locked->full_name,
                    ['before_values' => ['is_active' => ! $active], 'after_values' => ['is_active' => $active]],
                    $request,
                );
            }

            return $locked;
        });
    }
}
