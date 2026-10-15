<?php

namespace App\Modules\SeasonPilgrims\Actions\Pilgrims;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Models\Pilgrim;
use App\Modules\SeasonPilgrims\Models\PilgrimIdentityDocument;
use App\Modules\SeasonPilgrims\Support\IdentityDocumentDuplicateGuard;
use App\Modules\SeasonPilgrims\Support\IdentityMasking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AddIdentityDocument
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly IdentityDocumentDuplicateGuard $duplicates,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Pilgrim $pilgrim, array $data, Request $request): PilgrimIdentityDocument
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $pilgrim, $data, $request, $tenant): PilgrimIdentityDocument {
            $type = (string) $data['document_type'];
            $country = (string) $data['issuing_country'];
            $number = trim((string) $data['document_number']);

            $this->duplicates->assertUnique($type, $country, $number);

            $document = $this->duplicates->guard($type, $country, $number, fn () => PilgrimIdentityDocument::query()->create([
                'pilgrim_id' => $pilgrim->id,
                'document_type' => $type,
                'issuing_country' => $country,
                'document_number' => $number,
                'created_by' => $actor->id,
            ]));

            $this->security->record(AuthorizationSecurityEvent::PILGRIM_IDENTITY_DOCUMENT_ADDED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'pilgrim_identity_document_id' => $document->id,
                'pilgrim_name' => $pilgrim->full_name,
                'after_values' => [
                    'document_type' => $type,
                    'issuing_country' => $document->issuing_country,
                    'document_number' => IdentityMasking::mask($number),
                ],
                'metadata' => ['pilgrim_id' => $pilgrim->id],
            ], $request);

            return $document;
        });
    }
}
