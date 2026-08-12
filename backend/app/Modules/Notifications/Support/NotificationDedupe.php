<?php

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Enums\NotificationType;

final class NotificationDedupe
{
    public static function buildKey(
        NotificationType|string $type,
        ?string $entityType,
        int|string|null $entityId,
        int $recipientUserId,
        string $bucket,
    ): string {
        $typeValue = $type instanceof NotificationType ? $type->value : $type;
        $entityTypeValue = $entityType ?? '-';
        $entityIdValue = $entityId === null || $entityId === '' ? '-' : (string) $entityId;

        return mb_substr(
            "{$typeValue}:{$entityTypeValue}:{$entityIdValue}:{$recipientUserId}:{$bucket}",
            0,
            191,
        );
    }
}
