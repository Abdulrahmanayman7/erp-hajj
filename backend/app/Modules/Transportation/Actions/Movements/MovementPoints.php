<?php

namespace App\Modules\Transportation\Actions\Movements;

use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportPoint;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Shared Movement validation: two different, active, same-tenant points
 * (locked so a concurrent delete cannot slip in) and an arrival after the
 * departure.
 */
final class MovementPoints
{
    public static function assertValid(int $originId, int $destinationId): void
    {
        if ($originId === $destinationId) {
            throw TransportationDomainException::pointsSame();
        }

        $ids = [$originId, $destinationId];
        sort($ids);

        $points = TransportPoint::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        foreach (['origin_point_id' => $originId, 'destination_point_id' => $destinationId] as $field => $id) {
            $point = $points->get($id);
            if ($point === null) {
                throw TransportationDomainException::linkInvalid($field);
            }
            if (! $point->is_active) {
                throw TransportationDomainException::pointInactive();
            }
        }
    }

    public static function assertWindow(Carbon $departure, Carbon $arrival): void
    {
        if (! $arrival->greaterThan($departure)) {
            throw ValidationException::withMessages([
                'planned_arrival_at' => 'وقت الوصول المخطط يجب أن يكون بعد وقت الانطلاق.',
            ]);
        }
    }
}
