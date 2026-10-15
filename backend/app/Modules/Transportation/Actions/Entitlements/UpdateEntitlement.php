<?php

namespace App\Modules\Transportation\Actions\Entitlements;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\Transportation\Models\TransportEntitlement;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Changing an entitlement never rewrites passenger snapshots; mismatches
 * surface live (I10).
 */
final class UpdateEntitlement
{
    public function __construct(private readonly TransportAudit $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, TransportEntitlement $entitlement, array $data, Request $request): TransportEntitlement
    {
        return DB::transaction(function () use ($actor, $entitlement, $data, $request): TransportEntitlement {
            /** @var TransportEntitlement $locked */
            $locked = TransportEntitlement::query()->whereKey($entitlement->id)->lockForUpdate()->firstOrFail();
            SeasonGuard::assertWritable($locked->season()->firstOrFail());

            $changes = Arr::only($data, ['vehicle_class', 'service_level', 'notes']);
            $before = TransportAudit::values($locked->only(array_keys($changes)));

            $locked->fill($changes);
            if ($locked->vehicle_class === null && $locked->service_level === null) {
                throw ValidationException::withMessages(['vehicle_class' => 'حدد فئة المركبة أو مستوى الخدمة على الأقل.']);
            }
            $locked->save();

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_ENTITLEMENT_UPDATED, $actor->id, 'transport_entitlement', $locked->id, null, null, [
                'before_values' => $before,
                'after_values' => TransportAudit::values($locked->only(array_keys($changes))),
                'metadata' => ['season_id' => $locked->season_id, 'season_program_id' => $locked->season_program_id],
            ], $request);

            return $locked->refresh()->load('program');
        });
    }
}
