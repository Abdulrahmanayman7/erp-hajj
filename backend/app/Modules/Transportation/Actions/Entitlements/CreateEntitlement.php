<?php

namespace App\Modules\Transportation\Actions\Entitlements;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonProgram;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportEntitlement;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ADR-0018 T9: one typed entitlement per SeasonProgram. The Program is a
 * read-only reference of the same season.
 */
final class CreateEntitlement
{
    public function __construct(private readonly TransportAudit $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Season $season, array $data, Request $request): TransportEntitlement
    {
        return DB::transaction(function () use ($actor, $season, $data, $request): TransportEntitlement {
            SeasonGuard::assertWritable($season);

            /** @var SeasonProgram|null $program */
            $program = SeasonProgram::query()
                ->where('season_id', $season->id)
                ->whereKey((int) $data['season_program_id'])
                ->first();

            if ($program === null) {
                throw TransportationDomainException::linkInvalid('season_program_id');
            }

            if (TransportEntitlement::query()->where('season_id', $season->id)->where('season_program_id', $program->id)->exists()) {
                throw TransportationDomainException::entitlementDuplicate();
            }

            try {
                $entitlement = TransportEntitlement::query()->create([
                    'season_id' => $season->id,
                    'season_program_id' => $program->id,
                    'vehicle_class' => $data['vehicle_class'] ?? null,
                    'service_level' => $data['service_level'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $actor->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw TransportationDomainException::entitlementDuplicate();
            }

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_ENTITLEMENT_CREATED, $actor->id, 'transport_entitlement', $entitlement->id, $program->code, $program->name, [
                'after_values' => TransportAudit::values($entitlement->only(['vehicle_class', 'service_level'])),
                'metadata' => ['season_id' => $season->id, 'season_program_id' => $program->id],
            ], $request);

            return $entitlement->load('program');
        });
    }
}
