<?php

namespace App\Modules\Transportation\Actions\Points;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportPoint;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateTransportPoint
{
    public function __construct(private readonly TransportAudit $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, TransportPoint $point, array $data, Request $request): TransportPoint
    {
        return DB::transaction(function () use ($actor, $point, $data, $request): TransportPoint {
            /** @var TransportPoint $locked */
            $locked = TransportPoint::query()->whereKey($point->id)->lockForUpdate()->firstOrFail();

            $changes = [];
            if (array_key_exists('code', $data)) {
                $changes['code'] = trim((string) $data['code']);
                if (TransportPoint::query()->where('code', $changes['code'])->whereKeyNot($locked->id)->exists()) {
                    throw TransportationDomainException::pointCodeTaken();
                }
            }
            if (array_key_exists('name', $data)) {
                $changes['name'] = trim((string) $data['name']);
            }
            if (array_key_exists('point_type', $data)) {
                $changes['point_type'] = $data['point_type'];
            }
            if (array_key_exists('notes', $data)) {
                $changes['notes'] = $data['notes'];
            }

            $before = TransportAudit::values($locked->only(array_keys($changes)));

            try {
                $locked->update($changes);
            } catch (UniqueConstraintViolationException) {
                throw TransportationDomainException::pointCodeTaken();
            }

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_POINT_UPDATED, $actor->id, 'transport_point', $locked->id, $locked->code, $locked->name, [
                'before_values' => $before,
                'after_values' => $changes,
            ], $request);

            return $locked->refresh();
        });
    }
}
