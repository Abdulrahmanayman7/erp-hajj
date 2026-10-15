<?php

namespace App\Modules\Transportation\Actions\ExternalDrivers;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Transportation\Models\TransportExternalDriver;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateExternalDriver
{
    public function __construct(private readonly TransportAudit $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data, Request $request): TransportExternalDriver
    {
        return DB::transaction(function () use ($actor, $data, $request): TransportExternalDriver {
            $driver = TransportExternalDriver::query()->create([
                'full_name' => trim((string) $data['full_name']),
                'phone' => $data['phone'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_active' => true,
                'created_by' => $actor->id,
            ]);

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_EXTERNAL_DRIVER_CREATED, $actor->id, 'transport_external_driver', $driver->id, null, $driver->full_name, [
                'after_values' => ['full_name' => $driver->full_name, 'phone' => $driver->phone],
            ], $request);

            return $driver;
        });
    }
}
