<?php

namespace App\Modules\OrganizationStructure\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Contracts\Models\Contract;
use App\Modules\Decisions\Models\Decision;
use App\Modules\Documents\Enums\DocumentLinkableType;
use App\Modules\Documents\Support\DocumentReferenceValidator;
use App\Modules\Employees\Models\Employee;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\OrganizationStructure\Exceptions\OrganizationDomainException;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\Tasks\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeleteOrganizationUnit
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly DocumentReferenceValidator $documents,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, OrganizationUnit $unit, Request $request): void
    {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $unit, $request, $tenant): void {
            $locked = OrganizationUnit::query()->whereKey($unit->id)->lockForUpdate()->firstOrFail();

            if (OrganizationUnit::query()->where('parent_id', $locked->id)->exists()) {
                throw OrganizationDomainException::hasChildren();
            }

            if (Employee::query()->where('organization_unit_id', $locked->id)->exists()) {
                throw OrganizationDomainException::inUse();
            }

            if (Contract::query()->where('organization_unit_id', $locked->id)->exists()) {
                throw OrganizationDomainException::inUse();
            }

            if (Meeting::query()->where('organization_unit_id', $locked->id)->exists()) {
                throw OrganizationDomainException::inUse();
            }

            if (Decision::query()->where('organization_unit_id', $locked->id)->exists()) {
                throw OrganizationDomainException::inUse();
            }

            if (Task::query()->where('organization_unit_id', $locked->id)->exists()) {
                throw OrganizationDomainException::inUse();
            }

            $this->documents->assertNoDocumentsLinked(DocumentLinkableType::OrganizationUnit, (int) $locked->id);

            $snapshot = [
                'id' => $locked->id,
                'code' => $locked->code,
                'name' => $locked->name,
            ];

            $locked->delete();

            $this->security->record(AuthorizationSecurityEvent::ORGANIZATION_UNIT_DELETED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'organization_unit' => $snapshot,
            ], $request);
        });
    }
}
