<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\PurchaseApprovalMatrixStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Support\PurchaseApprovalMatrixPublishValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PublishPurchaseApprovalMatrix
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly PurchaseApprovalMatrixPublishValidator $validator,
    ) {}

    /**
     * @param  array{effective_from?: mixed}  $data
     */
    public function execute(
        User $actor,
        PurchaseApprovalMatrix $matrix,
        array $data,
        Request $request,
    ): PurchaseApprovalMatrix {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $matrix, $data, $request, $tenant): PurchaseApprovalMatrix {
            /** @var PurchaseApprovalMatrix $locked */
            $locked = PurchaseApprovalMatrix::query()->whereKey($matrix->id)->lockForUpdate()->firstOrFail();

            if ($locked->isPublished()) {
                throw SupplierDomainException::matrixAlreadyPublished();
            }

            if (! $locked->isDraft()) {
                throw SupplierDomainException::matrixNotDraft();
            }

            $this->validator->assertPublishable($locked);

            $priorPublished = PurchaseApprovalMatrix::query()
                ->where('status', PurchaseApprovalMatrixStatus::Published)
                ->whereKeyNot($locked->id)
                ->lockForUpdate()
                ->get();

            $maxPublishedVersion = (int) PurchaseApprovalMatrix::query()
                ->whereIn('status', [
                    PurchaseApprovalMatrixStatus::Published,
                    PurchaseApprovalMatrixStatus::Retired,
                ])
                ->where('version_no', '>', 0)
                ->lockForUpdate()
                ->max('version_no');

            $nextVersion = $maxPublishedVersion > 0 ? $maxPublishedVersion + 1 : 1;
            $now = now();
            $effectiveFrom = array_key_exists('effective_from', $data) && $data['effective_from'] !== null
                ? $data['effective_from']
                : $now;

            $retiredIds = [];
            foreach ($priorPublished as $prior) {
                $prior->forceFill([
                    'status' => PurchaseApprovalMatrixStatus::Retired,
                ])->save();
                $retiredIds[] = $prior->id;

                $this->security->record(AuthorizationSecurityEvent::MATRIX_RETIRED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'entity_type' => 'purchase_approval_matrix',
                    'entity_id' => $prior->id,
                    'version_no' => $prior->version_no,
                    'superseded_by' => $locked->id,
                    'status' => PurchaseApprovalMatrixStatus::Retired->value,
                ], $request);
            }

            $locked->forceFill([
                'status' => PurchaseApprovalMatrixStatus::Published,
                'version_no' => $nextVersion,
                'effective_from' => $effectiveFrom,
                'published_at' => $now,
                'published_by' => $actor->id,
            ])->save();

            $this->security->record(AuthorizationSecurityEvent::MATRIX_PUBLISHED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'purchase_approval_matrix',
                'entity_id' => $locked->id,
                'version_no' => $locked->version_no,
                'status' => $locked->status->value,
                'retired_prior_ids' => $retiredIds,
            ], $request);

            return $locked->fresh(['rules', 'levels', 'creator', 'publisher']) ?? $locked;
        });
    }
}
