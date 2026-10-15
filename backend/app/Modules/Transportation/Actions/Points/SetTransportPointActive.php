<?php

namespace App\Modules\Transportation\Actions\Points;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Transportation\Models\TransportPoint;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SetTransportPointActive
{
    public function __construct(private readonly TransportAudit $audit) {}

    public function execute(User $actor, TransportPoint $point, bool $active, Request $request): TransportPoint
    {
        return DB::transaction(function () use ($actor, $point, $active, $request): TransportPoint {
            /** @var TransportPoint $locked */
            $locked = TransportPoint::query()->whereKey($point->id)->lockForUpdate()->firstOrFail();

            if ($locked->is_active !== $active) {
                $locked->update(['is_active' => $active]);

                $this->audit->record(
                    $active ? AuthorizationSecurityEvent::TRANSPORT_POINT_ACTIVATED : AuthorizationSecurityEvent::TRANSPORT_POINT_DEACTIVATED,
                    $actor->id, 'transport_point', $locked->id, $locked->code, $locked->name,
                    ['before_values' => ['is_active' => ! $active], 'after_values' => ['is_active' => $active]],
                    $request,
                );
            }

            return $locked;
        });
    }
}
