<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S04 Phase II-B WP-01 / C1: tenant-scoped live IBAN claim ownership by account family.
 * Version-row fingerprints remain non-unique so same-IBAN supersession is possible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_bank_iban_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->char('iban_fingerprint', 64);
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('account_key', 64);
            $table->foreignId('holder_bank_account_id')
                ->constrained('supplier_bank_accounts')
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'iban_fingerprint'],
                'uq_bank_iban_claim_tenant_fp',
            );
            $table->index(['tenant_id', 'account_key'], 'supplier_bank_iban_claims_family_idx');
            $table->index(['tenant_id', 'supplier_id'], 'supplier_bank_iban_claims_supplier_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_bank_iban_claims');
    }
};
