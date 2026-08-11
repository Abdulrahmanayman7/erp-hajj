<?php

namespace App\Modules\Decisions\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Decisions\Enums\DecisionStatus;
use App\Modules\Decisions\Exceptions\DecisionDomainException;
use App\Modules\Decisions\Models\Decision;
use App\Modules\Decisions\Support\DecisionNumberGenerator;
use App\Modules\Decisions\Support\DecisionReferenceValidator;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateDecision
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly DecisionNumberGenerator $numbers,
        private readonly DecisionReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data, Request $request): Decision
    {
        $tenant = $this->tenantContext->require();

        try {
            return DB::transaction(function () use ($actor, $data, $request, $tenant): Decision {
                $recommendationId = array_key_exists('source_recommendation_id', $data) && $data['source_recommendation_id'] !== null
                    ? (int) $data['source_recommendation_id']
                    : null;

                $recommendation = $this->references->resolveConvertibleRecommendation($recommendationId);

                $title = isset($data['title']) ? trim((string) $data['title']) : '';
                $body = isset($data['body']) ? trim((string) $data['body']) : '';

                $organizationUnitId = array_key_exists('organization_unit_id', $data)
                    ? ($data['organization_unit_id'] !== null ? (int) $data['organization_unit_id'] : null)
                    : null;
                $issuedById = array_key_exists('issued_by_employee_id', $data)
                    ? ($data['issued_by_employee_id'] !== null ? (int) $data['issued_by_employee_id'] : null)
                    : null;
                $responsibleId = array_key_exists('responsible_employee_id', $data)
                    ? ($data['responsible_employee_id'] !== null ? (int) $data['responsible_employee_id'] : null)
                    : null;

                if ($recommendation !== null) {
                    if ($title === '') {
                        $title = $recommendation->title;
                    }
                    if ($body === '') {
                        $body = trim((string) ($recommendation->description ?? ''));
                    }
                    if (! array_key_exists('organization_unit_id', $data)) {
                        $organizationUnitId = $recommendation->meeting?->organization_unit_id;
                    }
                    if (! array_key_exists('responsible_employee_id', $data)) {
                        $responsibleId = $recommendation->owner_employee_id;
                    }
                }

                if ($title === '' || $body === '') {
                    throw DecisionDomainException::recommendationInvalid();
                }

                $this->references->assertDateRange(
                    $data['effective_date'] ?? null,
                    $data['due_date'] ?? null,
                );

                $unit = $this->references->resolveAssignableOrganizationUnit($organizationUnitId);
                $issuer = $this->references->resolveAssignableEmployee($issuedById);
                $responsible = $this->references->resolveAssignableEmployee($responsibleId);

                $decision = new Decision([
                    'title' => $title,
                    'body' => $body,
                    'notes' => $data['notes'] ?? null,
                    'status' => DecisionStatus::Draft,
                    'source_recommendation_id' => $recommendation?->id,
                    'organization_unit_id' => $unit?->id,
                    'issued_by_employee_id' => $issuer?->id,
                    'responsible_employee_id' => $responsible?->id,
                    'effective_date' => $data['effective_date'] ?? null,
                    'due_date' => $data['due_date'] ?? null,
                    'created_by' => $actor->id,
                ]);
                $decision->decision_number = $this->numbers->next();
                $decision->save();

                $auditPayload = [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'decision_id' => $decision->id,
                    'decision_number' => $decision->decision_number,
                    'status' => $decision->status->value,
                    'from_recommendation' => $recommendation !== null,
                ];
                if ($recommendation !== null) {
                    $auditPayload['source_recommendation_id'] = $recommendation->id;
                }

                $this->security->record(AuthorizationSecurityEvent::DECISION_CREATED, $auditPayload, $request);

                return $this->loadRelations($decision);
            });
        } catch (QueryException $e) {
            if ($this->isUniqueRecommendationViolation($e)) {
                throw DecisionDomainException::alreadyCreatedFromRecommendation();
            }
            if ($this->isUniqueNumberViolation($e)) {
                throw DecisionDomainException::numberTaken();
            }
            throw $e;
        }
    }

    private function loadRelations(Decision $decision): Decision
    {
        return $decision->load([
            'organizationUnit',
            'issuedByEmployee',
            'responsibleEmployee',
            'createdBy',
            'sourceRecommendation.meeting',
            'statusTransitions.performer',
        ]);
    }

    private function isUniqueRecommendationViolation(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'decisions_source_recommendation_unique')
            || (str_contains($message, 'source_recommendation_id') && str_contains($message, 'Duplicate'));
    }

    private function isUniqueNumberViolation(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'decisions_tenant_number_unique')
            || (str_contains($message, 'decision_number') && str_contains($message, 'Duplicate'));
    }
}
