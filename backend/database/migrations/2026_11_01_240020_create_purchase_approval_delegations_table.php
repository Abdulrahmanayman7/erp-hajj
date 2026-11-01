<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S04 Phase IV WP05: depth-1 purchase approval delegations (from≠to enforced in app). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_approval_delegations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('max_amount', 14, 2);
            $table->char('currency', 3)->default('SAR');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->foreignId('authorized_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'from_user_id', 'to_user_id'], 'pad_tenant_from_to_idx');
            $table->index(['tenant_id', 'to_user_id', 'revoked_at', 'starts_at', 'ends_at'], 'pad_tenant_to_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_approval_delegations');
    }
};
