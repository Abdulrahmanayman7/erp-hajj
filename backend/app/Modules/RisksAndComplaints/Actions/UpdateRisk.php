<?php

namespace App\Modules\RisksAndComplaints\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\RisksAndComplaints\Models\OperationalRisk;
use App\Modules\RisksAndComplaints\Support\RiskLifecycle;
use App\Modules\RisksAndComplaints\Support\RiskReferenceValidator;
use App\Modules\SeasonPilgrims\Context\SeasonContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateRisk
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SeasonContext $seasonContext,
        private readonly RiskReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, OperationalRisk $risk, array $data, Request $request): OperationalRisk
    {
        $tenant = $this->tenantContext->require();
        $season = $this->seasonContext->require();

        return DB::transaction(function () use ($actor, $risk, $data, $request, $tenant, $season): OperationalRisk {
            $locked = OperationalRisk::query()->whereKey($risk->id)->lockForUpdate()->firstOrFail();
            RiskLifecycle::assertContentEditable($locked->status);

            $before = [
                'title' => $locked->title,
                'description' => $locked->description,
                'season_pilgrim_id' => $locked->season_pilgrim_id,
                'group_id' => $locked->group_id,
                'facility_id' => $locked->facility_id,
                'transport_season_vehicle_id' => $locked->transport_season_vehicle_id,
                'project_id' => $locked->project_id,
            ];

            if (array_key_exists('title', $data) && $data['title'] !== null) {
                $locked->title = trim((string) $data['title']);
            }
            if (array_key_exists('description', $data)) {
                $locked->description = $data['description'];
            }

            $links = $this->references->resolveOptionalLinks($season, $data, partial: true);
            foreach (['season_pilgrim_id', 'group_id', 'facility_id', 'transport_season_vehicle_id', 'project_id'] as $field) {
                if (array_key_exists($field, $data)) {
                    $locked->{$field} = $links[$field];
                }
            }

            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::RISK_UPDATED, [
                'tenant_id' => $tenant->id,
                'season_id' => $locked->season_id,
                'actor_id' => $actor->id,
                'risk_id' => $locked->id,
                'risk_number' => $locked->risk_number,
                'before' => $before,
                'after' => [
                    'title' => $locked->title,
                    'description' => $locked->description,
                    'season_pilgrim_id' => $locked->season_pilgrim_id,
                    'group_id' => $locked->group_id,
                    'facility_id' => $locked->facility_id,
                    'transport_season_vehicle_id' => $locked->transport_season_vehicle_id,
                    'project_id' => $locked->project_id,
                ],
            ], $request);

            return $locked->load(['ownerEmployee', 'createdBy', 'treatment.ownerEmployee', 'approvedBy']);
        });
    }
}
