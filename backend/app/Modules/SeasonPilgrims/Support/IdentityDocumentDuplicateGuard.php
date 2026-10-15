<?php

namespace App\Modules\SeasonPilgrims\Support;

use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\PilgrimIdentityDocument;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * F17/F18: the same (type, issuing country, number) may not belong to two
 * pilgrims in a tenant. The conflict is surfaced with the existing pilgrim
 * id; pilgrims are never merged automatically.
 */
final class IdentityDocumentDuplicateGuard
{
    public function assertUnique(string $type, string $issuingCountry, string $number, ?int $ignoreDocumentId = null): void
    {
        $existing = $this->find($type, $issuingCountry, $number, $ignoreDocumentId);

        if ($existing !== null) {
            throw SeasonPilgrimDomainException::identityDocumentDuplicate((int) $existing->pilgrim_id);
        }
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $write
     * @return T
     */
    public function guard(string $type, string $issuingCountry, string $number, Closure $write): mixed
    {
        try {
            return $write();
        } catch (UniqueConstraintViolationException) {
            $existing = $this->find($type, $issuingCountry, $number);

            throw SeasonPilgrimDomainException::identityDocumentDuplicate((int) ($existing?->pilgrim_id ?? 0));
        }
    }

    private function find(string $type, string $issuingCountry, string $number, ?int $ignoreDocumentId = null): ?PilgrimIdentityDocument
    {
        return PilgrimIdentityDocument::query()
            ->where('document_type', $type)
            ->where('issuing_country', mb_strtoupper(trim($issuingCountry)))
            ->where('normalized_number', PilgrimIdentityDocument::normalizeNumber($number))
            ->when($ignoreDocumentId !== null, fn ($q) => $q->whereKeyNot($ignoreDocumentId))
            ->first();
    }
}
