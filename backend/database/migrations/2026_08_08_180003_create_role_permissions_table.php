<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Role ↔ global permission assignments (tenant_id denormalized from role).
 * See docs/09-modules/02-users-and-authorization/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['role_id', 'permission_id']);
            $table->unique(['tenant_id', 'role_id', 'permission_id']);
            $table->index(['tenant_id', 'role_id']);
            $table->index('permission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
