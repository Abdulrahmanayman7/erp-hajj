<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\EligibilityConfigStatus;
use App\Modules\SuppliersAndProcurement\Models\SupplierEligibilityRequirementSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Creates DRAFT sets. To build an approved set with definitions, create the draft,
 * add definitions, then call {@see self::approveDraft()} (approved sets are immutable).
 *
 * @extends Factory<SupplierEligibilityRequirementSet>
 */
class SupplierEligibilityRequirementSetFactory extends Factory
{
    protected $model = SupplierEligibilityRequirementSet::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'config_key' => 'default',
            'name' => 'متطلبات التأهيل',
            'status' => EligibilityConfigStatus::Draft,
            'version_no' => 1,
            'authored_by' => User::factory(),
            'last_edited_by' => null,
            'approved_by' => null,
            'approved_at' => null,
        ];
    }

    public static function approveDraft(SupplierEligibilityRequirementSet $set, User|int $approver): SupplierEligibilityRequirementSet
    {
        $set->forceFill([
            'status' => EligibilityConfigStatus::Approved,
            'approved_by' => $approver instanceof User ? $approver->id : $approver,
            'approved_at' => now(),
        ])->save();

        return $set->refresh();
    }
}
