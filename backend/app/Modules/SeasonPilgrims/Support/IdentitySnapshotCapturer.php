<?php

namespace App\Modules\SeasonPilgrims\Support;

use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\PilgrimIdentityDocument;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimIdentitySnapshot;

/**
 * Copies the allow-listed identity values from the current Pilgrim (and an
 * optionally selected identity document of that pilgrim) into the seasonal
 * snapshot. Only callers that already verified the snapshot is unlocked
 * may use this.
 */
final class IdentitySnapshotCapturer
{
    public function capture(
        SeasonPilgrim $seasonPilgrim,
        ?int $documentId,
        bool $keepCurrentDocument,
        int $actorId,
    ): SeasonPilgrimIdentitySnapshot {
        $pilgrim = $seasonPilgrim->pilgrim()->firstOrFail();
        /** @var SeasonPilgrimIdentitySnapshot|null $snapshot */
        $snapshot = $seasonPilgrim->snapshot()->first();

        if ($documentId === null && $keepCurrentDocument && $snapshot !== null) {
            $documentId = $snapshot->pilgrim_identity_document_id;
        }

        $document = null;
        if ($documentId !== null) {
            $document = PilgrimIdentityDocument::query()
                ->whereKey($documentId)
                ->where('pilgrim_id', $pilgrim->id)
                ->first();

            if ($document === null) {
                throw SeasonPilgrimDomainException::identityDocumentInvalid();
            }
        }

        $values = [
            'pilgrim_identity_document_id' => $document?->id,
            'full_name' => $pilgrim->full_name,
            'gender' => $pilgrim->gender?->value,
            'nationality' => $pilgrim->nationality,
            'document_type' => $document?->document_type->value,
            'document_issuing_country' => $document?->issuing_country,
            'document_number' => $document?->document_number,
            'captured_at' => now(),
            'captured_by' => $actorId,
        ];

        if ($snapshot === null) {
            return SeasonPilgrimIdentitySnapshot::query()->create([
                'season_pilgrim_id' => $seasonPilgrim->id,
                ...$values,
            ]);
        }

        $snapshot->update($values);

        return $snapshot;
    }
}
