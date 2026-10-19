<?php

namespace App\Modules\Accommodation\Support;

use App\Modules\Accommodation\Exceptions\AccommodationDomainException;
use App\Modules\Accommodation\Models\AccommodationStructure;

final class AccommodationHierarchy
{
    public static function assertParent(int $facilityId, ?int $parentId, ?int $selfId = null): void
    {
        if ($parentId === null) {
            return;
        }

        if ($selfId !== null && $parentId === $selfId) {
            throw AccommodationDomainException::structureCycle();
        }

        $cursor = $parentId;
        $seen = 0;

        while ($cursor !== null) {
            if (++$seen > 64 || ($selfId !== null && $cursor === $selfId)) {
                throw AccommodationDomainException::structureCycle();
            }

            $node = AccommodationStructure::query()
                ->where('facility_id', $facilityId)
                ->whereKey($cursor)
                ->first();

            if ($node === null) {
                throw AccommodationDomainException::invalidReference();
            }

            $cursor = $node->parent_id === null ? null : (int) $node->parent_id;
        }
    }
}
