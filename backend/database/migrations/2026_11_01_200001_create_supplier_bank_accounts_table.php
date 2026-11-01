<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S04 Phase II-B WP-01: versioned supplier bank accounts.
 *
 * - current_slot: family edit head (0) vs historical (id) — orthogonal to primary.
 * - primary_flag: generated uniqueness for at most one primary per supplier (IIB-D11).
 * - IBAN uniqueness is NOT on this table (C1 → supplier_bank_iban_claims).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('account_key', 64);
            $table->unsignedInteger('version_no');
            $table->string('label')->nullable();
            $table->string('account_holder_name');
            $table->string('bank_name');
            $table->text('iban_ciphertext')->nullable();
            $table->text('account_ciphertext')->nullable();
            $table->char('iban_last4', 4)->nullable();
            $table->char('account_last4', 4)->nullable();
            $table->char('iban_fingerprint', 64)->nullable();
            $table->char('currency', 3)->default('SAR');
            $table->string('status', 16)->default('pending');
            $table->unsignedBigInteger('current_slot')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->unsignedTinyInteger('primary_flag')
                ->nullable()
                ->storedAs('case when is_primary = 1 then 1 else null end');
            $table->foreignId('supersedes_id')->nullable()
                ->constrained('supplier_bank_accounts')->restrictOnDelete();
            $table->foreignId('evidence_document_id')->nullable()
                ->constrained('documents')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('last_edited_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'supplier_id', 'account_key', 'version_no'],
                'supplier_bank_accounts_family_version_unique',
            );
            $table->unique(
                ['tenant_id', 'supplier_id', 'account_key', 'current_slot'],
                'supplier_bank_accounts_family_current_unique',
            );
            $table->unique(
                ['supplier_id', 'primary_flag'],
                'supplier_bank_accounts_one_primary_unique',
            );

            $table->index(['tenant_id', 'supplier_id'], 'supplier_bank_accounts_supplier_idx');
            $table->index(['tenant_id', 'status'], 'supplier_bank_accounts_status_idx');
            $table->index(['tenant_id', 'iban_fingerprint'], 'supplier_bank_accounts_iban_fp_idx');
            $table->index(['evidence_document_id'], 'supplier_bank_accounts_evidence_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_bank_accounts');
    }
};
