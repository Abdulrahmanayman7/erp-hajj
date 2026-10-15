<?php

namespace App\Modules\Transportation\Actions\ExternalDrivers;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Transportation\Models\TransportExternalDriver;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class UpdateExternalDriver
{
    public function __construct(private readonly TransportAudit $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, TransportExternalDriver $driver, array $data, Request $request): TransportExternalDriver
    {
        return DB::transaction(function () use ($actor, $driver, $data, $request): TransportExternalDriver {
            /** @var TransportExternalDriver $locked */
            $locked = TransportExternalDriver::query()->whereKey($driver->id)->lockForUpdate()->firstOrFail();

            $changes = Arr::only($data, ['full_name', 'phone', 'notes']);
            if (array_key_exists('full_name', $changes)) {
                $changes['full_name'] = trim((string) $changes['full_name']);
            }

            $before = $locked->only(array_keys($changes));
            $locked->update($changes);

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_EXTERNAL_DRIVER_UPDATED, $actor->id, 'transport_external_driver', $locked->id, null, $locked->full_name, [
                'before_values' => $before,
                'after_values' => $changes,
            ], $request);

            return $locked->refresh();
        });
    }
}
