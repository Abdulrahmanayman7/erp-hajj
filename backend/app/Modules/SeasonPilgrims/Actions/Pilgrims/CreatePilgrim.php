<?php

namespace App\Modules\SeasonPilgrims\Actions\Pilgrims;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Models\Pilgrim;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Creates a (possibly provisional) pilgrim — identity documents are
 * optional at creation (F18).
 */
final class CreatePilgrim
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data, Request $request): Pilgrim
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $data, $request, $tenant): Pilgrim {
            $pilgrim = Pilgrim::query()->create([
                'full_name' => trim((string) $data['full_name']),
                'gender' => $data['gender'] ?? null,
                'nationality' => isset($data['nationality']) ? mb_strtoupper((string) $data['nationality']) : null,
                'phone' => $data['phone'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->security->record(AuthorizationSecurityEvent::PILGRIM_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'pilgrim_id' => $pilgrim->id,
                'pilgrim_name' => $pilgrim->full_name,
            ], $request);

            return $pilgrim->load('identityDocuments');
        });
    }
}
