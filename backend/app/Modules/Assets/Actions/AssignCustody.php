<?php

namespace App\Modules\Assets\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Shared\CorrelationId;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Enums\CustodyStatus;
use App\Modules\Assets\Exceptions\AssetDomainException;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\AssetCustody;
use App\Modules\Assets\Support\AssetReferenceValidator;
use App\Modules\Assets\Support\AssetStatusTransitionRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AssignCustody
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AssetReferenceValidator $references,
        private readonly AssetStatusTransitionRecorder $transitions,
        private readonly CorrelationId $correlationId,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Asset $asset, array $data, Request $request): Asset
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $asset, $data, $request, $tenant): Asset {
            /** @var Asset $locked */
            $locked = Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== AssetStatus::Available) {
                throw AssetDomainException::notAvailable();
            }

            if ($locked->current_custody_id !== null) {
                throw AssetDomainException::alreadyAssigned();
            }

            $activeExists = AssetCustody::query()
                ->where('asset_id', $locked->id)
                ->where('status', CustodyStatus::Active->value)
                ->exists();

            if ($activeExists) {
                throw AssetDomainException::alreadyAssigned();
            }

            $employee = $this->references->resolveActiveEmployee((int) $data['employee_id']);
            $condition = $this->references->assertCondition(
                isset($data['condition_at_assignment']) ? (string) $data['condition_at_assignment'] : null,
            );

            $custody = new AssetCustody([
                'asset_id' => $locked->id,
                'employee_id' => $employee->id,
                'status' => CustodyStatus::Active,
                'assigned_at' => now(),
                'expected_return_at' => $data['expected_return_at'] ?? null,
                'assigned_by' => $actor->id,
                'condition_at_assignment' => $condition,
                'assignment_notes' => isset($data['assignment_notes'])
                    ? (string) $data['assignment_notes']
                    : null,
                'correlation_id' => $this->correlationId->get(),
            ]);

            try {
                $custody->save();
            } catch (\Throwable) {
                throw AssetDomainException::custodyConflict();
            }

            // Re-check under lock after insert (race safety).
            $activeCount = AssetCustody::query()
                ->where('asset_id', $locked->id)
                ->where('status', CustodyStatus::Active->value)
                ->count();

            if ($activeCount !== 1) {
                throw AssetDomainException::custodyConflict();
            }

            $from = $locked->status;
            $locked->status = AssetStatus::InUse;
            $locked->current_custody_id = $custody->id;
            $locked->save();

            $this->transitions->record(
                $locked,
                $from,
                AssetStatus::InUse,
                $actor,
                null,
                (int) $custody->id,
            );

            $this->security->record(AuthorizationSecurityEvent::ASSET_ASSIGNED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'asset_id' => $locked->id,
                'asset_number' => $locked->asset_number,
                'custody_id' => $custody->id,
                'custody_number' => $custody->custody_number,
                'employee_id' => $employee->id,
                'from_status' => $from->value,
                'to_status' => AssetStatus::InUse->value,
            ], $request);

            return $locked->load([
                'category',
                'warehouse',
                'organizationUnit',
                'currentCustody.employee',
                'createdBy',
            ]);
        });
    }
}
