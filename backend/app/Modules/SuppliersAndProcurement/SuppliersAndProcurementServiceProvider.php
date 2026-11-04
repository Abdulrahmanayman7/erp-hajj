<?php

namespace App\Modules\SuppliersAndProcurement;

use App\Modules\SuppliersAndProcurement\Models\GeoPlace;
use App\Modules\SuppliersAndProcurement\Models\GoodsReceipt;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalDelegation;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrix;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrixLevel;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalMatrixRule;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApprovalSlaPolicy;
use App\Modules\SuppliersAndProcurement\Models\PurchaseApproverAuthority;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroup;
use App\Modules\SuppliersAndProcurement\Models\PurchaseOrder;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierCategory;
use App\Modules\SuppliersAndProcurement\Models\SupplierCategoryCoverage;
use App\Modules\SuppliersAndProcurement\Models\SupplierContact;
use App\Modules\SuppliersAndProcurement\Models\SupplierDocumentRecord;
use App\Modules\SuppliersAndProcurement\Models\SupplierEligibilityRequirementSet;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluation;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationAppeal;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationCriteriaPack;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluationCriterion;
use App\Modules\SuppliersAndProcurement\Models\SupplierOffer;
use App\Modules\SuppliersAndProcurement\Policies\GeoPlacePolicy;
use App\Modules\SuppliersAndProcurement\Policies\GoodsReceiptPolicy;
use App\Modules\SuppliersAndProcurement\Policies\ProcurementRfqPolicy;
use App\Modules\SuppliersAndProcurement\Policies\PurchaseApprovalDelegationPolicy;
use App\Modules\SuppliersAndProcurement\Policies\PurchaseApprovalMatrixPolicy;
use App\Modules\SuppliersAndProcurement\Policies\PurchaseApprovalSlaPolicyPolicy;
use App\Modules\SuppliersAndProcurement\Policies\PurchaseApproverAuthorityPolicy;
use App\Modules\SuppliersAndProcurement\Policies\PurchaseDemandGroupPolicy;
use App\Modules\SuppliersAndProcurement\Policies\PurchaseOrderPolicy;
use App\Modules\SuppliersAndProcurement\Policies\PurchaseRequestPolicy;
use App\Modules\SuppliersAndProcurement\Policies\SupplierCategoryPolicy;
use App\Modules\SuppliersAndProcurement\Policies\SupplierEligibilityRequirementSetPolicy;
use App\Modules\SuppliersAndProcurement\Policies\SupplierEvaluationAppealPolicy;
use App\Modules\SuppliersAndProcurement\Policies\SupplierEvaluationCriteriaPackPolicy;
use App\Modules\SuppliersAndProcurement\Policies\SupplierEvaluationPolicy;
use App\Modules\SuppliersAndProcurement\Policies\SupplierOfferPolicy;
use App\Modules\SuppliersAndProcurement\Policies\SupplierPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SuppliersAndProcurementServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(SupplierCategory::class, SupplierCategoryPolicy::class);
        Gate::policy(GeoPlace::class, GeoPlacePolicy::class);
        Gate::policy(SupplierEligibilityRequirementSet::class, SupplierEligibilityRequirementSetPolicy::class);
        Gate::policy(SupplierEvaluationCriteriaPack::class, SupplierEvaluationCriteriaPackPolicy::class);
        Gate::policy(SupplierEvaluation::class, SupplierEvaluationPolicy::class);
        Gate::policy(SupplierEvaluationAppeal::class, SupplierEvaluationAppealPolicy::class);
        Gate::policy(PurchaseRequest::class, PurchaseRequestPolicy::class);
        Gate::policy(ProcurementRfq::class, ProcurementRfqPolicy::class);
        Gate::policy(SupplierOffer::class, SupplierOfferPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(GoodsReceipt::class, GoodsReceiptPolicy::class);
        Gate::policy(PurchaseDemandGroup::class, PurchaseDemandGroupPolicy::class);
        Gate::policy(PurchaseApprovalMatrix::class, PurchaseApprovalMatrixPolicy::class);
        Gate::policy(PurchaseApproverAuthority::class, PurchaseApproverAuthorityPolicy::class);
        Gate::policy(PurchaseApprovalDelegation::class, PurchaseApprovalDelegationPolicy::class);
        Gate::policy(PurchaseApprovalSlaPolicy::class, PurchaseApprovalSlaPolicyPolicy::class);

        Route::bind('purchase_request', function (string $value): PurchaseRequest {
            $actor = auth()->user();
            if ($actor === null || $actor->tenant_id === null) {
                abort(404);
            }

            return PurchaseRequest::query()->whereKey($value)->firstOrFail();
        });

        Route::bind('rfq', function (string $value): ProcurementRfq {
            $actor = auth()->user();
            if ($actor === null || $actor->tenant_id === null) {
                abort(404);
            }

            return ProcurementRfq::query()->whereKey($value)->firstOrFail();
        });

        Route::bind('offer', function (string $value): SupplierOffer {
            $actor = auth()->user();
            if ($actor === null || $actor->tenant_id === null) {
                abort(404);
            }

            return SupplierOffer::query()->whereKey($value)->firstOrFail();
        });

        Route::bind('po', function (string $value): PurchaseOrder {
            $actor = auth()->user();
            if ($actor === null || $actor->tenant_id === null) {
                abort(404);
            }

            return PurchaseOrder::query()->whereKey($value)->firstOrFail();
        });

        Route::bind('grn', function (string $value): GoodsReceipt {
            $actor = auth()->user();
            if ($actor === null || $actor->tenant_id === null) {
                abort(404);
            }

            return GoodsReceipt::query()->whereKey($value)->firstOrFail();
        });

        Route::bind('purchase_demand_group', function (string $value): PurchaseDemandGroup {
            $actor = auth()->user();
            if ($actor === null || $actor->tenant_id === null) {
                abort(404);
            }

            return PurchaseDemandGroup::query()->whereKey($value)->firstOrFail();
        });

        // Short route variable (Symfony limits names to 32 chars); tenant scope => cross-tenant 404.
        Route::bind('requirement_set', function (string $value): SupplierEligibilityRequirementSet {
            $actor = auth()->user();
            if ($actor === null || $actor->tenant_id === null) {
                abort(404);
            }

            return SupplierEligibilityRequirementSet::query()->whereKey($value)->firstOrFail();
        });

        Route::bind('criteria_pack', function (string $value): SupplierEvaluationCriteriaPack {
            $actor = auth()->user();
            if ($actor === null || $actor->tenant_id === null) {
                abort(404);
            }

            return SupplierEvaluationCriteriaPack::query()->whereKey($value)->firstOrFail();
        });

        Route::bind('evaluation', function (string $value): SupplierEvaluation {
            $actor = auth()->user();
            if ($actor === null || $actor->tenant_id === null) {
                abort(404);
            }

            return SupplierEvaluation::query()->whereKey($value)->firstOrFail();
        });

        Route::bind('appeal', function (string $value): SupplierEvaluationAppeal {
            $actor = auth()->user();
            if ($actor === null || $actor->tenant_id === null) {
                abort(404);
            }

            return SupplierEvaluationAppeal::query()->whereKey($value)->firstOrFail();
        });

        Route::bind('criterion', function (string $value, $route): SupplierEvaluationCriterion {
            $actor = auth()->user();
            if ($actor === null || $actor->tenant_id === null) {
                abort(404);
            }

            $pack = $route->parameter('criteria_pack');
            $packId = $pack instanceof SupplierEvaluationCriteriaPack ? (int) $pack->id : (int) $pack;

            return SupplierEvaluationCriterion::query()
                ->whereKey($value)
                ->where('pack_id', $packId)
                ->firstOrFail();
        });

        Route::bind('supplier_document_record', function (string $value, $route): SupplierDocumentRecord {
            $actor = auth()->user();
            if ($actor === null || $actor->tenant_id === null) {
                abort(404);
            }

            $supplierId = $this->routeSupplierId($route->parameter('supplier'));

            return SupplierDocumentRecord::query()
                ->whereKey($value)
                ->where('supplier_id', $supplierId)
                ->firstOrFail();
        });

        Route::bind('supplier_contact', function (string $value, $route): SupplierContact {
            $actor = auth()->user();
            if ($actor === null || $actor->tenant_id === null) {
                abort(404);
            }

            $supplierId = $this->routeSupplierId($route->parameter('supplier'));

            return SupplierContact::query()
                ->whereKey($value)
                ->where('supplier_id', $supplierId)
                ->firstOrFail();
        });

        Route::bind('supplier_coverage', function (string $value, $route): SupplierCategoryCoverage {
            $actor = auth()->user();
            if ($actor === null || $actor->tenant_id === null) {
                abort(404);
            }

            $supplierId = $this->routeSupplierId($route->parameter('supplier'));

            return SupplierCategoryCoverage::query()
                ->whereKey($value)
                ->where('supplier_id', $supplierId)
                ->firstOrFail();
        });

        Route::bind('purchase_approval_matrix', function (string $value): PurchaseApprovalMatrix {
            $actor = auth()->user();
            if ($actor === null || $actor->tenant_id === null) {
                abort(404);
            }

            return PurchaseApprovalMatrix::query()->whereKey($value)->firstOrFail();
        });

        Route::bind('matrix_rule', function (string $value, $route): PurchaseApprovalMatrixRule {
            $actor = auth()->user();
            if ($actor === null || $actor->tenant_id === null) {
                abort(404);
            }

            $matrix = $route->parameter('purchase_approval_matrix');
            $matrixId = $matrix instanceof PurchaseApprovalMatrix ? (int) $matrix->id : (int) $matrix;

            return PurchaseApprovalMatrixRule::query()
                ->whereKey($value)
                ->where('matrix_id', $matrixId)
                ->firstOrFail();
        });

        Route::bind('matrix_level', function (string $value, $route): PurchaseApprovalMatrixLevel {
            $actor = auth()->user();
            if ($actor === null || $actor->tenant_id === null) {
                abort(404);
            }

            $matrix = $route->parameter('purchase_approval_matrix');
            $matrixId = $matrix instanceof PurchaseApprovalMatrix ? (int) $matrix->id : (int) $matrix;

            return PurchaseApprovalMatrixLevel::query()
                ->whereKey($value)
                ->where('matrix_id', $matrixId)
                ->firstOrFail();
        });

        Route::bind('purchase_approver_authority', function (string $value): PurchaseApproverAuthority {
            $actor = auth()->user();
            if ($actor === null || $actor->tenant_id === null) {
                abort(404);
            }

            return PurchaseApproverAuthority::query()->whereKey($value)->firstOrFail();
        });

        Route::bind('purchase_approval_delegation', function (string $value): PurchaseApprovalDelegation {
            $actor = auth()->user();
            if ($actor === null || $actor->tenant_id === null) {
                abort(404);
            }

            return PurchaseApprovalDelegation::query()->whereKey($value)->firstOrFail();
        });

        Route::bind('purchase_approval_sla_policy', function (string $value): PurchaseApprovalSlaPolicy {
            $actor = auth()->user();
            if ($actor === null || $actor->tenant_id === null) {
                abort(404);
            }

            return PurchaseApprovalSlaPolicy::query()->whereKey($value)->firstOrFail();
        });
    }

    private function routeSupplierId(mixed $supplier): int
    {
        if ($supplier instanceof Supplier) {
            return (int) $supplier->id;
        }

        return (int) $supplier;
    }
}
