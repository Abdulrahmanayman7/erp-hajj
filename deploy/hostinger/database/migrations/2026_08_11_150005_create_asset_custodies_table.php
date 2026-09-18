<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only asset custodies (active | returned).
 *
 * @see docs/09-modules/11-assets-and-custodies/DATA_MODEL.md
 * @see docs/10-decisions/ADR-0012-ASSET-CUSTODY-AND-OWNERSHIP.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_custodies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('custody_number', 20);
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->string('status', 32);
            $table->timestamp('assigned_at');
            $table->timestamp('expected_return_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('returned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('condition_at_assignment', 32)->nullable();
            $table->string('condition_at_return', 32)->nullable();
            $table->string('assignment_notes', 1000)->nullable();
            $table->string('return_notes', 1000)->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'custody_number'], 'asset_custodies_tenant_number_unique');
            $table->index(['tenant_id', 'asset_id', 'status'], 'asset_custodies_tenant_asset_status_idx');
            $table->index(['tenant_id', 'employee_id', 'status'], 'asset_custodies_tenant_employee_status_idx');
            $table->index(['tenant_id', 'assigned_at']);
            $table->index(['tenant_id', 'expected_return_at']);
        });

        Schema::table('asset_status_transitions', function (Blueprint $table): void {
            $table->foreign('custody_id')
                ->references('id')
                ->on('asset_custodies')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('asset_status_transitions', function (Blueprint $table): void {
            $table->dropForeign(['custody_id']);
        });

        Schema::dropIfExists('asset_custodies');
    }
};
