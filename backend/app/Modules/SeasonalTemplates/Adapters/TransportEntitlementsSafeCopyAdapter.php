<?php

namespace App\Modules\SeasonalTemplates\Adapters;

use App\Models\User;
use App\Modules\SeasonalTemplates\Support\SafeCopyAllowlist;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonProgram;
use App\Modules\Transportation\Models\TransportEntitlement;

/**
 * transport_entitlements: program (mapped by code), vehicle_class, service_level, notes.
 * Unique per (season, program), so the program code is the natural key.
 */
final class TransportEntitlementsSafeCopyAdapter extends AbstractSafeCopyAdapter
{
    public function entityKind(): string
    {
        return SafeCopyAllowlist::TRANSPORT_ENTITLEMENTS;
    }

    protected function sourceCandidates(Season $source): array
    {
        $rows = [];
        $entitlements = TransportEntitlement::query()
            ->where('season_id', $source->id)
            ->with('program')
            ->get()
            ->filter(static fn (TransportEntitlement $e): bool => $e->program !== null)
            ->sortBy(static fn (TransportEntitlement $e): string => $e->program->code);

        foreach ($entitlements as $entitlement) {
            $rows[$entitlement->program->code] = [
                'source_id' => $entitlement->id,
                'fields' => $this->fieldsOf($entitlement),
            ];
        }

        return $rows;
    }

    protected function targetRows(Season $target): array
    {
        $rows = [];
        $entitlements = TransportEntitlement::query()
            ->where('season_id', $target->id)
            ->with('program')
            ->get()
            ->filter(static fn (TransportEntitlement $e): bool => $e->program !== null)
            ->sortBy(static fn (TransportEntitlement $e): string => $e->program->code);

        foreach ($entitlements as $entitlement) {
            $rows[$entitlement->program->code] = [
                'id' => $entitlement->id,
                'fields' => $this->fieldsOf($entitlement),
            ];
        }

        return $rows;
    }

    protected function dependencyConflict(Season $target, string $key, array $fields, array $selection): ?array
    {
        if ($this->targetProgramId($target, $key) !== null) {
            return null;
        }

        // The program is created earlier in this same run only when it is part of the selection.
        if ($this->willBeCopied($selection, SafeCopyAllowlist::SEASON_PROGRAMS, $key)) {
            return null;
        }

        return ['dependency' => SafeCopyAllowlist::SEASON_PROGRAMS, 'program_code' => $key];
    }

    protected function insertTarget(Season $target, array $fields, User $actor): ?int
    {
        $programId = $this->targetProgramId($target, (string) $fields['program_code']);
        if ($programId === null) {
            return null;
        }

        $entitlement = new TransportEntitlement([
            'season_id' => $target->id,
            'season_program_id' => $programId,
            'vehicle_class' => $fields['vehicle_class'],
            'service_level' => $fields['service_level'],
            'notes' => $fields['notes'],
            'created_by' => $actor->id,
        ]);
        $entitlement->save();

        return $entitlement->id;
    }

    protected function updateTarget(int $targetId, array $fields): void
    {
        TransportEntitlement::query()->findOrFail($targetId)->update([
            'vehicle_class' => $fields['vehicle_class'],
            'service_level' => $fields['service_level'],
            'notes' => $fields['notes'],
        ]);
    }

    private function targetProgramId(Season $target, string $code): ?int
    {
        $id = SeasonProgram::query()
            ->where('season_id', $target->id)
            ->where('code', $code)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldsOf(TransportEntitlement $entitlement): array
    {
        return $this->allowlisted([
            'program_code' => $entitlement->program->code,
            'vehicle_class' => $entitlement->vehicle_class?->value,
            'service_level' => $entitlement->service_level?->value,
            'notes' => $entitlement->notes,
        ]);
    }
}
