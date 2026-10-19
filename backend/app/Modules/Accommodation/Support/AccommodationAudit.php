<?php

namespace App\Modules\Accommodation\Support;

use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use DateTimeInterface;
use Illuminate\Http\Request;

final class AccommodationAudit
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function record(
        string $event,
        ?int $actorId,
        string $entityType,
        int $entityId,
        ?string $entityNumber,
        ?string $entityLabel,
        array $context,
        ?Request $request,
    ): void {
        $this->security->record($event, [
            'tenant_id' => $this->tenantContext->require()->id,
            'actor_id' => $actorId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_number' => $entityNumber,
            'entity_label' => $entityLabel,
            ...$context,
        ], $request);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function values(array $values): array
    {
        return array_map(fn (mixed $value): mixed => $value instanceof DateTimeInterface
            ? $value->format(DATE_ATOM)
            : $value, $values);
    }
}
