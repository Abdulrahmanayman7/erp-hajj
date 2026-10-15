<?php

namespace App\Modules\SeasonPilgrims\Actions\Pilgrims;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Models\PilgrimIdentityDocument;
use App\Modules\SeasonPilgrims\Support\IdentityDocumentDuplicateGuard;
use App\Modules\SeasonPilgrims\Support\IdentityMasking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Corrects a current identity document (e.g. renewed passport). Seasonal
 * snapshots keep the values they captured (F19).
 */
final class UpdateIdentityDocument
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly IdentityDocumentDuplicateGuard $duplicates,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, PilgrimIdentityDocument $document, array $data, Request $request): PilgrimIdentityDocument
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $document, $data, $request, $tenant): PilgrimIdentityDocument {
            /** @var PilgrimIdentityDocument $locked */
            $locked = PilgrimIdentityDocument::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();

            $type = (string) ($data['document_type'] ?? $locked->document_type->value);
            $country = (string) ($data['issuing_country'] ?? $locked->issuing_country);
            $number = trim((string) ($data['document_number'] ?? $locked->document_number));

            $this->duplicates->assertUnique($type, $country, $number, $locked->id);

            $before = $this->values($locked);
            $this->duplicates->guard($type, $country, $number, fn () => $locked->update([
                'document_type' => $type,
                'issuing_country' => $country,
                'document_number' => $number,
            ]));
            $after = $this->values($locked);

            $this->security->record(AuthorizationSecurityEvent::PILGRIM_IDENTITY_DOCUMENT_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'pilgrim_identity_document_id' => $locked->id,
                'pilgrim_name' => $locked->pilgrim?->full_name,
                'before_values' => array_diff_assoc($before, $after),
                'after_values' => array_diff_assoc($after, $before),
                'metadata' => ['pilgrim_id' => $locked->pilgrim_id],
            ], $request);

            return $locked;
        });
    }

    /**
     * @return array<string, string|null>
     */
    private function values(PilgrimIdentityDocument $document): array
    {
        return IdentityMasking::maskValues([
            'document_type' => $document->document_type->value,
            'issuing_country' => $document->issuing_country,
            'document_number' => $document->document_number,
        ]);
    }
}
