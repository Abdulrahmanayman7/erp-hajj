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

final class UpdateDecision
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly DecisionReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Decision $decision, array $data, Request $request): Decision
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $decision, $data, $request, $tenant): Decision {
            $locked = Decision::query()->whereKey($decision->id)->lockForUpdate()->firstOrFail();
            $this->references->assertDraftEditable($locked);

            $effective = array_key_exists('effective_date', $data) ? $data['effective_date'] : $locked->effective_date?->toDateString();
            $due = array_key_exists('due_date', $data) ? $data['due_date'] : $locked->due_date?->toDateString();
            $this->references->assertDateRange($effective, $due);

            if (array_key_exists('organization_unit_id', $data)) {
                $unit = $this->references->resolveAssignableOrganizationUnit(
                    $data['organization_unit_id'] !== null ? (int) $data['organization_unit_id'] : null,
                    $locked->organization_unit_id,
                );
                $locked->organization_unit_id = $unit?->id;
            }

            if (array_key_exists('issued_by_employee_id', $data)) {
                $issuer = $this->references->resolveAssignableEmployee(
                    $data['issued_by_employee_id'] !== null ? (int) $data['issued_by_employee_id'] : null,
                    $locked->issued_by_employee_id,
                );
                $locked->issued_by_employee_id = $issuer?->id;
            }

            if (array_key_exists('responsible_employee_id', $data)) {
                $responsible = $this->references->resolveAssignableEmployee(
                    $data['responsible_employee_id'] !== null ? (int) $data['responsible_employee_id'] : null,
                    $locked->responsible_employee_id,
                );
                $locked->responsible_employee_id = $responsible?->id;
            }

            if (array_key_exists('title', $data)) {
                $locked->title = $data['title'];
            }
            if (array_key_exists('body', $data)) {
                $locked->body = $data['body'];
            }
            if (array_key_exists('notes', $data)) {
                $locked->notes = $data['notes'];
            }
            if (array_key_exists('effective_date', $data)) {
                $locked->effective_date = $data['effective_date'];
            }
            if (array_key_exists('due_date', $data)) {
                $locked->due_date = $data['due_date'];
            }

            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::DECISION_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'decision_id' => $locked->id,
                'decision_number' => $locked->decision_number,
            ], $request);

            return $locked->load([
                'organizationUnit',
                'issuedByEmployee',
                'responsibleEmployee',
                'createdBy',
                'sourceRecommendation.meeting',
                'statusTransitions.performer',
            ]);
        });
    }
}
