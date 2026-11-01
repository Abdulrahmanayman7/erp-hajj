<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S04 Phase II-B WP-01: durable editor history for hard SoD (IIB-D4).
 * Parallel to supplier_eligibility_set_editors (II-A M5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_bank_account_editors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('bank_account_id')
                ->constrained('supplier_bank_accounts')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('role', 16); // creator | editor
            $table->timestamp('first_edited_at');
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'bank_account_id', 'user_id'],
                'supplier_bank_account_editors_unique',
            );
            $table->index(['tenant_id', 'bank_account_id'], 'supplier_bank_account_editors_account_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_bank_account_editors');
    }
};
