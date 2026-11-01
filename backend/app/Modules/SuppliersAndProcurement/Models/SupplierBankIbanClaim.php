<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Live tenant IBAN claim owned by one account family (C1).
 * Cross-family reassignment of account_key/supplier_id is forbidden by BankIbanClaimService.
 */
class SupplierBankIbanClaim extends Model implements TenantOwned
{
    use UsesTenantScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'iban_fingerprint',
        'supplier_id',
        'account_key',
        'holder_bank_account_id',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'iban_fingerprint',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function holder(): BelongsTo
    {
        return $this->belongsTo(SupplierBankAccount::class, 'holder_bank_account_id');
    }
}
