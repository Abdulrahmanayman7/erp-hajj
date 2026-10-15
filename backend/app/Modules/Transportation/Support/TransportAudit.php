<?php

namespace App\Modules\Transportation\Support;

use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Http\Request;

/**
 * Records Transportation audit events through Core/Audit with an explicit
 * entity descriptor (no AuditEventMapper hints needed).
 */
final class TransportAudit
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $context  before_values, after_values, reason, from_status, to_status, metadata
     */
    public function record(
        string $event,
        ?int $actorId,
        string $entityType,
        int $entityId,
        ?string $entityNumber = null,
        ?string $entityLabel = null,
        array $context = [],
        ?Request $request = null,
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
     * Scalar audit values (enums → value, dates → ISO-8601).
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function values(array $values): array
    {
        return array_map(fn (mixed $value): mixed => match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            default => $value,
        }, $values);
    }
}
