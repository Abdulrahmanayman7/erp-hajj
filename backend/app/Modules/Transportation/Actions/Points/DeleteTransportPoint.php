<?php

namespace App\Modules\Transportation\Actions\Points;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportPoint;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Hard delete only while no Movement references the point; otherwise the
 * point must be deactivated.
 */
final class DeleteTransportPoint
{
    public function __construct(private readonly TransportAudit $audit) {}

    public function execute(User $actor, TransportPoint $point, Request $request): void
    {
        DB::transaction(function () use ($actor, $point, $request): void {
            /** @var TransportPoint $locked */
            $locked = TransportPoint::query()->whereKey($point->id)->lockForUpdate()->firstOrFail();

            if ($locked->isUsed()) {
                throw TransportationDomainException::pointInUse();
            }

            $locked->delete();

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_POINT_DELETED, $actor->id, 'transport_point', $locked->id, $locked->code, $locked->name, [], $request);
        });
    }
}
