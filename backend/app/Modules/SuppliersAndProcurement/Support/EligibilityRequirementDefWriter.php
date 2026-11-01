<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\SupplierDocumentRequirementDef;
use App\Modules\SuppliersAndProcurement\Models\SupplierEligibilityRequirementSet;
use Illuminate\Validation\ValidationException;

/**
 * Writes requirement definitions of a DRAFT set only (approved sets are immutable — C1;
 * the model layer rejects any write against an approved set as a second defense).
 */
final class EligibilityRequirementDefWriter
{
    /**
     * Replace all definitions of the draft atomically (caller owns the transaction).
     *
     * @param  list<array<string, mixed>>  $defs
     */
    public function replace(SupplierEligibilityRequirementSet $draft, array $defs): void
    {
        $this->assertDraft($draft);
        $this->assertNoDuplicates($defs);

        SupplierDocumentRequirementDef::query()
            ->where('requirement_set_id', $draft->id)
            ->get()
            ->each(fn (SupplierDocumentRequirementDef $def): ?bool => $def->delete());

        foreach ($defs as $def) {
            SupplierDocumentRequirementDef::query()->create([
                'requirement_set_id' => $draft->id,
                'requirement_key' => $def['requirement_key'],
                'label_ar' => $def['label_ar'],
                'description' => $def['description'] ?? null,
                'supplier_type' => $def['supplier_type'],
                'country_code' => $def['country_code'] ?? null,
                'supplier_category_id' => $def['supplier_category_id'] ?? null,
                'is_mandatory' => (bool) ($def['is_mandatory'] ?? true),
                'default_validity_days' => $def['default_validity_days'] ?? null,
            ]);
        }
    }

    public function copy(SupplierEligibilityRequirementSet $from, SupplierEligibilityRequirementSet $toDraft): void
    {
        $this->assertDraft($toDraft);

        foreach ($from->requirementDefs()->get() as $def) {
            SupplierDocumentRequirementDef::query()->create([
                'requirement_set_id' => $toDraft->id,
                'requirement_key' => $def->requirement_key,
                'label_ar' => $def->label_ar,
                'description' => $def->description,
                'supplier_type' => $def->supplier_type->value,
                'country_code' => $def->country_code,
                'supplier_category_id' => $def->supplier_category_id,
                'is_mandatory' => $def->is_mandatory,
                'default_validity_days' => $def->default_validity_days,
            ]);
        }
    }

    /**
     * M4 Action-level guard: definitions are only ever written to a DRAFT set (re-read, not trusted).
     */
    private function assertDraft(SupplierEligibilityRequirementSet $set): void
    {
        $fresh = SupplierEligibilityRequirementSet::query()->whereKey($set->id)->first();
        if ($fresh === null || ! $fresh->isDraft()) {
            throw SupplierDomainException::eligibilityConfigImmutable();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $defs
     */
    private function assertNoDuplicates(array $defs): void
    {
        $seen = [];
        foreach ($defs as $index => $def) {
            $country = isset($def['country_code']) && $def['country_code'] !== null && $def['country_code'] !== ''
                ? strtoupper((string) $def['country_code'])
                : '';
            $signature = implode('|', [
                (string) $def['requirement_key'],
                (string) $def['supplier_type'],
                $country,
                (string) ($def['supplier_category_id'] ?? 0),
            ]);

            if (isset($seen[$signature])) {
                throw ValidationException::withMessages([
                    "requirement_defs.{$index}.requirement_key" => ['متطلب مكرر بنفس نطاق التطبيق.'],
                ]);
            }
            $seen[$signature] = true;
        }
    }
}
