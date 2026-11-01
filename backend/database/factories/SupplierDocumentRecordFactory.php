<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Documents\Models\Document;
use App\Modules\SuppliersAndProcurement\Enums\DocumentVerificationStatus;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierDocumentRecord;
use App\Modules\SuppliersAndProcurement\Models\SupplierDocumentRequirementDef;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierDocumentRecord>
 */
class SupplierDocumentRecordFactory extends Factory
{
    protected $model = SupplierDocumentRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'requirement_def_id' => SupplierDocumentRequirementDef::factory(),
            'document_id' => Document::factory(),
            'verification_status' => DocumentVerificationStatus::PendingVerification,
            'expires_at' => null,
            'uploaded_by' => User::factory(),
            'verified_by' => null,
            'verified_at' => null,
            'rejection_reason' => null,
            'supersedes_id' => null,
        ];
    }
}
