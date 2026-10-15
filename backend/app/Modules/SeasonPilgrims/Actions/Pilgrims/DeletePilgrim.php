<?php

namespace App\Modules\SeasonPilgrims\Actions\Pilgrims;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\Pilgrim;
use App\Modules\SeasonPilgrims\Models\PilgrimIdentityDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Hard delete only for a pilgrim with no season participation.
 */
final class DeletePilgrim
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Pilgrim $pilgrim, Request $request): void
    {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $pilgrim, $request, $tenant): void {
            /** @var Pilgrim $locked */
            $locked = Pilgrim::query()->whereKey($pilgrim->id)->lockForUpdate()->firstOrFail();

            if ($locked->seasonPilgrims()->exists()) {
                throw SeasonPilgrimDomainException::pilgrimInUse();
            }

            $context = [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'pilgrim_id' => $locked->id,
                'pilgrim_name' => $locked->full_name,
                'metadata' => ['identity_documents_deleted' => $locked->identityDocuments()->count()],
            ];

            PilgrimIdentityDocument::query()->where('pilgrim_id', $locked->id)->delete();
            $locked->delete();

            $this->security->record(AuthorizationSecurityEvent::PILGRIM_DELETED, $context, $request);
        });
    }
}
