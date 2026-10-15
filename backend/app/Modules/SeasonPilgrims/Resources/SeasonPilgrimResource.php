<?php

namespace App\Modules\SeasonPilgrims\Resources;

use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimIdentitySnapshot;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimProgramChange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SeasonPilgrim
 */
class SeasonPilgrimResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SeasonPilgrim $sp */
        $sp = $this->resource;

        $payload = [
            'id' => $sp->id,
            'season_id' => $sp->season_id,
            'reference_number' => $sp->reference_number,
            'status' => $sp->status->value,
            'pilgrim' => $sp->relationLoaded('pilgrim') && $sp->pilgrim !== null ? [
                'id' => $sp->pilgrim->id,
                'full_name' => $sp->pilgrim->full_name,
            ] : null,
            'program' => $sp->relationLoaded('program') && $sp->program !== null ? [
                'id' => $sp->program->id,
                'code' => $sp->program->code,
                'name' => $sp->program->name,
            ] : null,
            'pilgrim_group' => $sp->relationLoaded('pilgrimGroup') && $sp->pilgrimGroup !== null ? [
                'id' => $sp->pilgrimGroup->id,
                'code' => $sp->pilgrimGroup->code,
                'name' => $sp->pilgrimGroup->name,
            ] : null,
            'family_group' => $sp->relationLoaded('familyGroup') && $sp->familyGroup !== null ? [
                'id' => $sp->familyGroup->id,
                'name' => $sp->familyGroup->name,
                'is_primary_member' => (bool) $sp->is_family_primary,
            ] : null,
            'registered_at' => $sp->registered_at?->toIso8601String(),
            'activated_at' => $sp->activated_at?->toIso8601String(),
            'completed_at' => $sp->completed_at?->toIso8601String(),
            'cancelled_at' => $sp->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $sp->cancellation_reason,
            'created_at' => $sp->created_at?->toIso8601String(),
            'updated_at' => $sp->updated_at?->toIso8601String(),
        ];

        if ($sp->relationLoaded('snapshot')) {
            $payload['identity_snapshot'] = $sp->snapshot !== null ? $this->snapshotPayload($sp->snapshot) : null;
        }

        if ($sp->relationLoaded('programChanges')) {
            $payload['program_history'] = $sp->programChanges
                ->sortBy('id')
                ->map(fn (SeasonPilgrimProgramChange $c) => [
                    'id' => $c->id,
                    'from_program' => $c->relationLoaded('fromProgram') && $c->fromProgram !== null
                        ? ['id' => $c->fromProgram->id, 'code' => $c->fromProgram->code, 'name' => $c->fromProgram->name]
                        : null,
                    'to_program' => $c->relationLoaded('toProgram') && $c->toProgram !== null
                        ? ['id' => $c->toProgram->id, 'code' => $c->toProgram->code, 'name' => $c->toProgram->name]
                        : null,
                    'reason' => $c->reason,
                    'changed_by' => $c->changed_by,
                    'created_at' => $c->created_at?->toIso8601String(),
                ])
                ->values()
                ->all();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotPayload(SeasonPilgrimIdentitySnapshot $snapshot): array
    {
        return [
            'full_name' => $snapshot->full_name,
            'gender' => $snapshot->gender,
            'nationality' => $snapshot->nationality,
            'document_type' => $snapshot->document_type,
            'document_issuing_country' => $snapshot->document_issuing_country,
            'document_number' => $snapshot->document_number,
            'source_identity_document_id' => $snapshot->pilgrim_identity_document_id,
            'captured_at' => $snapshot->captured_at?->toIso8601String(),
            'is_locked' => $snapshot->isLocked(),
            'locked_at' => $snapshot->locked_at?->toIso8601String(),
            'corrected_at' => $snapshot->corrected_at?->toIso8601String(),
            'correction_count' => $snapshot->correction_count,
        ];
    }
}
