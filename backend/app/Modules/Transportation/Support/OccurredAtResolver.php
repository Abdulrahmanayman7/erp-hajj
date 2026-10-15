<?php

namespace App\Modules\Transportation\Support;

use App\Models\User;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use Illuminate\Support\Carbon;

/**
 * ADR-0018 I7: occurred_at defaults to now; a future value is rejected; a
 * value older than the configured tolerance is backdated and requires a
 * reason plus `transportation.correct_history`.
 */
final class OccurredAtResolver
{
    public function resolve(User $actor, ?string $occurredAt, ?string $reason): OccurredAt
    {
        $reason = $reason !== null && trim($reason) !== '' ? trim($reason) : null;
        $now = Carbon::now();

        if ($occurredAt === null || $occurredAt === '') {
            return new OccurredAt($now, false, $reason);
        }

        $at = Carbon::parse($occurredAt)->setTimezone($now->getTimezone());

        if ($at->greaterThan($now)) {
            throw TransportationDomainException::occurredAtFuture();
        }

        $tolerance = max(0, (int) config('transportation.backdate_tolerance_minutes', 15));
        $backdated = $at->lessThan($now->copy()->subMinutes($tolerance));

        if ($backdated) {
            if ($reason === null) {
                throw TransportationDomainException::backdateReasonRequired();
            }

            if (! $actor->hasPermission('transportation.correct_history')) {
                throw TransportationDomainException::backdateForbidden();
            }
        }

        return new OccurredAt($at, $backdated, $reason);
    }
}
