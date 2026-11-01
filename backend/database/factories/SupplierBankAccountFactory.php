<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\SupplierBankAccountStatus;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierBankAccount;
use App\Modules\SuppliersAndProcurement\Support\IbanFingerprint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SupplierBankAccount>
 */
class SupplierBankAccountFactory extends Factory
{
    protected $model = SupplierBankAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'account_key' => (string) Str::uuid(),
            'version_no' => 1,
            'label' => 'حساب تجريبي',
            'account_holder_name' => fake()->company(),
            'bank_name' => 'مصرف تجريبي',
            'iban_last4' => '7519',
            'currency' => 'SAR',
            'status' => SupplierBankAccountStatus::Pending,
            'current_slot' => 0,
            'is_primary' => false,
            'created_by' => User::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (SupplierBankAccount $account): void {
            $iban = 'SA'.fake()->numerify(str_repeat('#', 22));
            $fp = app(IbanFingerprint::class)->fingerprint((int) $account->tenant_id, $iban);
            $account->forceFill([
                'iban_last4' => substr($iban, -4),
            ]);
            $account->storeEncryptedIdentifiers($iban, null, $fp);
        });
    }

    public function verified(): static
    {
        return $this->state(fn (): array => [
            'status' => SupplierBankAccountStatus::Verified,
        ]);
    }

    public function primary(): static
    {
        return $this->state(fn (): array => [
            'status' => SupplierBankAccountStatus::Verified,
            'is_primary' => true,
        ]);
    }
}
