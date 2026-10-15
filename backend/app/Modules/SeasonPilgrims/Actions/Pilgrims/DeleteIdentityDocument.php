<?php

namespace App\Modules\SeasonPilgrims\Actions\Pilgrims;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Models\PilgrimIdentityDocument;
use App\Modules\SeasonPilgrims\Support\IdentityMasking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Removes a current identity document. Snapshots that captured it keep
 * their copied values (the source link is nulled by the FK).
 */
final class DeleteIdentityDocument
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, PilgrimIdentityDocument $document, Request $request): void
    {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $document, $request, $tenant): void {
            $context = [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'pilgrim_identity_document_id' => $document->id,
                'pilgrim_name' => $document->pilgrim?->full_name,
                'before_values' => [
                    'document_type' => $document->document_type->value,
                    'issuing_country' => $document->issuing_country,
                    'document_number' => IdentityMasking::mask($document->document_number),
                ],
                'metadata' => ['pilgrim_id' => $document->pilgrim_id],
            ];

            $document->delete();

            $this->security->record(AuthorizationSecurityEvent::PILGRIM_IDENTITY_DOCUMENT_DELETED, $context, $request);
        });
    }
}
