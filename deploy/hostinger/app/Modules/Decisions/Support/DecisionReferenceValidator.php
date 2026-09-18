<?php

namespace App\Modules\Decisions\Support;

use App\Modules\Decisions\Enums\DecisionStatus;
use App\Modules\Decisions\Exceptions\DecisionDomainException;
use App\Modules\Decisions\Models\Decision;
use App\Modules\Decisions\Models\DecisionStatusTransition;
use App\Modules\Employees\Enums\EmployeeStatus;
use App\Modules\Employees\Models\Employee;
use App\Modules\Meetings\Enums\MeetingStatus;
use App\Modules\Meetings\Enums\RecommendationStatus;
use App\Modules\Meetings\Models\MeetingRecommendation;
use App\Modules\OrganizationStructure\Enums\OrganizationUnitStatus;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use Illuminate\Support\Carbon;

final class DecisionReferenceValidator
{
    public function resolveAssignableEmployee(?int $employeeId, ?int $currentEmployeeId = null): ?Employee
    {
        if ($employeeId === null) {
            return null;
        }

        $employee = Employee::query()->whereKey($employeeId)->first();

        if ($employee === null) {
            throw DecisionDomainException::employeeInvalid();
        }

        $keepingSame = $currentEmployeeId !== null && (int) $employee->id === (int) $currentEmployeeId;

        if (! $keepingSame && $employee->status !== EmployeeStatus::Active) {
            throw DecisionDomainException::employeeInvalid();
        }

        return $employee;
    }

    public function resolveAssignableOrganizationUnit(?int $unitId, ?int $currentUnitId = null): ?OrganizationUnit
    {
        if ($unitId === null) {
            return null;
        }

        $unit = OrganizationUnit::query()->whereKey($unitId)->first();

        if ($unit === null) {
            throw DecisionDomainException::organizationInvalid();
        }

        $keepingSame = $currentUnitId !== null && (int) $unit->id === (int) $currentUnitId;

        if (! $keepingSame && $unit->status !== OrganizationUnitStatus::Active) {
            throw DecisionDomainException::organizationInvalid();
        }

        return $unit;
    }

    public function assertDateRange(mixed $effectiveDate, mixed $dueDate): void
    {
        if ($effectiveDate === null || $dueDate === null || $effectiveDate === '' || $dueDate === '') {
            return;
        }

        $effective = Carbon::parse((string) $effectiveDate)->startOfDay();
        $due = Carbon::parse((string) $dueDate)->startOfDay();

        if ($due->lt($effective)) {
            throw DecisionDomainException::invalidDateRange();
        }
    }

    public function resolveConvertibleRecommendation(?int $recommendationId): ?MeetingRecommendation
    {
        if ($recommendationId === null) {
            return null;
        }

        $recommendation = MeetingRecommendation::query()
            ->with('meeting')
            ->whereKey($recommendationId)
            ->first();

        if ($recommendation === null) {
            throw DecisionDomainException::recommendationInvalid();
        }

        if ($recommendation->status !== RecommendationStatus::Final) {
            throw DecisionDomainException::recommendationInvalid();
        }

        $meeting = $recommendation->meeting;
        if ($meeting === null || $meeting->status !== MeetingStatus::Completed) {
            throw DecisionDomainException::recommendationInvalid();
        }

        $exists = Decision::query()
            ->where('source_recommendation_id', $recommendation->id)
            ->exists();

        if ($exists) {
            throw DecisionDomainException::alreadyCreatedFromRecommendation();
        }

        return $recommendation;
    }

    public function assertDraftEditable(Decision $decision): void
    {
        if (! $decision->status->isContentEditable()) {
            throw DecisionDomainException::immutable();
        }
    }

    public function assertDeletableDraft(Decision $decision): void
    {
        if ($decision->status !== DecisionStatus::Draft) {
            throw DecisionDomainException::deleteForbidden();
        }

        $count = DecisionStatusTransition::query()
            ->where('decision_id', $decision->id)
            ->count();

        if ($count > 0) {
            throw DecisionDomainException::deleteForbidden();
        }
    }
}
