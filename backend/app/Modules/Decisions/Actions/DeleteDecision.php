<?php

namespace App\Modules\Decisions\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Decisions\Models\Decision;
use App\Modules\Decisions\Support\DecisionReferenceValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeleteDecision
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly DecisionReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Decision $decision, Request $request): void
    {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $decision, $request, $tenant): void {
            $locked = Decision::query()->whereKey($decision->id)->lockForUpdate()->firstOrFail();
            $this->references->assertDeletableDraft($locked);

            $snapshot = [
                'id' => $locked->id,
                'decision_number' => $locked->decision_number,
                'title' => $locked->title,
            ];

            $locked->delete();

            $this->security->record(AuthorizationSecurityEvent::DECISION_DELETED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'decision' => $snapshot,
            ], $request);
        });
    }
}
