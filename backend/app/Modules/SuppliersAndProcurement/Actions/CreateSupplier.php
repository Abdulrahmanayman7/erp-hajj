<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\SupplierStatus;
use App\Modules\SuppliersAndProcurement\Enums\SupplierType;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Support\SupplierCategorySync;
use App\Modules\SuppliersAndProcurement\Support\SupplierLegalIdNormalizer;
use App\Modules\SuppliersAndProcurement\Support\SupplierReferenceValidator;
use App\Modules\SuppliersAndProcurement\Support\SupplierSimilarNameChecker;
use App\Modules\SuppliersAndProcurement\Support\SupplierTransitionRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateSupplier
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SupplierReferenceValidator $references,
        private readonly SupplierCategorySync $categorySync,
        private readonly SupplierTransitionRecorder $transitions,
        private readonly SupplierSimilarNameChecker $similarNames,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{supplier: Supplier, warnings: list<array<string, mixed>>}
     */
    public function execute(User $actor, array $data, Request $request): array
    {
        $tenant = $this->tenantContext->require();
        $categoryIds = array_map('intval', $data['category_ids'] ?? []);
        $legalType = isset($data['legal_id_type']) ? trim((string) $data['legal_id_type']) : null;
        $legalValue = $data['legal_id_value'] ?? null;
        $legalNormalized = SupplierLegalIdNormalizer::normalize($legalType, is_string($legalValue) ? $legalValue : null);

        // Bounded InnoDB deadlock / lock-wait retries. Numbering correctness is
        // the sequence primary-key upsert; attempts recover transient contention.
        return DB::transaction(function () use ($actor, $data, $request, $tenant, $categoryIds, $legalType, $legalValue, $legalNormalized): array {
            $this->references->assertLegalIdUnique($legalType, $legalNormalized);

            $categories = $this->references->resolveAssignableCategories($categoryIds);

            $supplier = new Supplier([
                'supplier_type' => SupplierType::from($data['supplier_type']),
                'display_name' => $data['display_name'],
                'legal_name' => $data['legal_name'] ?? null,
                'country_code' => strtoupper((string) $data['country_code']),
                'legal_id_type' => $legalType,
                'legal_id_value' => $legalValue,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);
            $supplier->status = SupplierStatus::Draft;
            $supplier->legal_id_normalized = $legalNormalized;
            $supplier->save();

            if ($categories->isNotEmpty()) {
                $this->categorySync->sync($supplier, $categories, $tenant->id);
            }

            $this->transitions->record($supplier, null, SupplierStatus::Draft, $actor, null, $request);

            $this->security->record(AuthorizationSecurityEvent::SUPPLIER_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'supplier_id' => $supplier->id,
                'supplier_number' => $supplier->supplier_number,
                'status' => $supplier->status->value,
            ], $request);

            $supplier->load(['categories', 'createdBy', 'statusTransitions.actor']);

            return [
                'supplier' => $supplier,
                'warnings' => $this->similarNames->warningsFor($supplier->display_name, (int) $supplier->id),
            ];
        }, 5);
    }
}
