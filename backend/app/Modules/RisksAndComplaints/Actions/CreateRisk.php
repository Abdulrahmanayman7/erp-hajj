<?php

namespace App\Modules\RisksAndComplaints\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\RisksAndComplaints\Enums\RiskStatus;
use App\Modules\RisksAndComplaints\Exceptions\RisksAndComplaintsDomainException;
use App\Modules\RisksAndComplaints\Models\OperationalRisk;
use App\Modules\RisksAndComplaints\Support\RiskReferenceValidator;
use App\Modules\RisksAndComplaints\Support\RiskScoreCalculator;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateRisk
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly RiskReferenceValidator $references,
        private readonly RiskScoreCalculator $scores,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Season $season, array $data, Request $request): OperationalRisk
    {
        $tenant = $this->tenantContext->require();

        try {
            return DB::transaction(function () use ($actor, $season, $data, $request, $tenant): OperationalRisk {
                $links = $this->references->resolveOptionalLinks($season, $data);

                $likelihood = array_key_exists('likelihood', $data) && $data['likelihood'] !== null
                    ? (int) $data['likelihood']
                    : null;
                $impact = array_key_exists('impact', $data) && $data['impact'] !== null
                    ? (int) $data['impact']
                    : null;

                if (($likelihood === null) xor ($impact === null)) {
                    throw RisksAndComplaintsDomainException::riskMatrixOutOfRange();
                }

                if ($likelihood !== null && $impact !== null) {
                    $this->scores->calculate($likelihood, $impact);
                }

                $risk = new OperationalRisk([
                    'season_id' => $season->id,
                    'title' => trim((string) $data['title']),
                    'description' => $data['description'] ?? null,
                    'status' => RiskStatus::Identified,
                    'likelihood' => $likelihood,
                    'impact' => $impact,
                    'created_by' => $actor->id,
                    'season_pilgrim_id' => $links['season_pilgrim_id'],
                    'group_id' => $links['group_id'],
                    'facility_id' => $links['facility_id'],
                    'transport_season_vehicle_id' => $links['transport_season_vehicle_id'],
                    'project_id' => $links['project_id'],
                ]);
                $risk->save();

                $this->security->record(AuthorizationSecurityEvent::RISK_CREATED, [
                    'tenant_id' => $tenant->id,
                    'season_id' => $season->id,
                    'actor_id' => $actor->id,
                    'risk_id' => $risk->id,
                    'risk_number' => $risk->risk_number,
                    'status' => $risk->status->value,
                    'after' => [
                        'title' => $risk->title,
                        'status' => $risk->status->value,
                    ],
                ], $request);

                return $this->load($risk);
            });
        } catch (QueryException $e) {
            if ($this->isUniqueNumberViolation($e)) {
                throw RisksAndComplaintsDomainException::riskNumberTaken();
            }
            throw $e;
        }
    }

    private function load(OperationalRisk $risk): OperationalRisk
    {
        return $risk->load(['ownerEmployee', 'createdBy', 'treatment.ownerEmployee', 'approvedBy']);
    }

    private function isUniqueNumberViolation(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'operational_risks_tenant_season_number_unique')
            || (str_contains($message, 'risk_number') && str_contains($message, 'Duplicate'));
    }
}
