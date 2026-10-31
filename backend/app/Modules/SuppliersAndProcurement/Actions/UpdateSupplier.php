<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\SupplierStatus;
use App\Modules\SuppliersAndProcurement\Enums\SupplierType;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Support\SupplierCategorySync;
use App\Modules\SuppliersAndProcurement\Support\SupplierLegalIdNormalizer;
use App\Modules\SuppliersAndProcurement\Support\SupplierReferenceValidator;
use App\Modules\SuppliersAndProcurement\Support\SupplierSimilarNameChecker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateSupplier
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SupplierReferenceValidator $references,
        private readonly SupplierCategorySync $categorySync,
        private readonly SupplierSimilarNameChecker $similarNames,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{supplier: Supplier, warnings: list<array<string, mixed>>}
     */
    public function execute(User $actor, Supplier $supplier, array $data, Request $request): array
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $supplier, $data, $request, $tenant): array {
            $locked = Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== SupplierStatus::Draft) {
                throw SupplierDomainException::notEditable();
            }

            if (array_key_exists('display_name', $data)) {
                $locked->display_name = $data['display_name'];
            }
            if (array_key_exists('legal_name', $data)) {
                $locked->legal_name = $data['legal_name'];
            }
            if (array_key_exists('supplier_type', $data)) {
                $locked->supplier_type = SupplierType::from($data['supplier_type']);
            }
            if (array_key_exists('country_code', $data)) {
                $locked->country_code = strtoupper((string) $data['country_code']);
            }
            if (array_key_exists('notes', $data)) {
                $locked->notes = $data['notes'];
            }

            if (array_key_exists('legal_id_type', $data) || array_key_exists('legal_id_value', $data)) {
                $type = array_key_exists('legal_id_type', $data)
                    ? trim((string) ($data['legal_id_type'] ?? ''))
                    : $locked->legal_id_type;
                $value = array_key_exists('legal_id_value', $data)
                    ? $data['legal_id_value']
                    : $locked->legal_id_value;
                $type = $type === '' ? null : $type;
                $normalized = SupplierLegalIdNormalizer::normalize($type, is_string($value) ? $value : null);
                $this->references->assertLegalIdUnique($type, $normalized, (int) $locked->id);
                $locked->legal_id_type = $type;
                $locked->legal_id_value = $value;
                $locked->legal_id_normalized = $normalized;
            }

            $locked->save();

            if (array_key_exists('category_ids', $data)) {
                $categoryIds = array_map('intval', $data['category_ids'] ?? []);
                $categories = $this->references->resolveAssignableCategories($categoryIds);
                $this->categorySync->sync($locked, $categories, $tenant->id);
            }

            $this->security->record(AuthorizationSecurityEvent::SUPPLIER_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'supplier_id' => $locked->id,
                'supplier_number' => $locked->supplier_number,
            ], $request);

            $locked->load(['categories', 'createdBy', 'statusTransitions.actor']);

            return [
                'supplier' => $locked,
                'warnings' => $this->similarNames->warningsFor($locked->display_name, (int) $locked->id),
            ];
        });
    }
}
