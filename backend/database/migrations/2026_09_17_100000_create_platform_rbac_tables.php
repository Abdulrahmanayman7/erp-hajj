<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal Platform RBAC (ADR-0003 / users DATA_MODEL recommendation).
 * Separate from tenant roles — platform_tenants.* never live on tenant role_permissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 255);
            $table->string('description', 500)->nullable();
            $table->boolean('is_system')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('platform_role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_role_id')->constrained('platform_roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['platform_role_id', 'permission_id'], 'platform_role_permission_unique');
        });

        Schema::create('platform_user_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('platform_role_id')->constrained('platform_roles')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'platform_role_id'], 'platform_user_role_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_user_roles');
        Schema::dropIfExists('platform_role_permissions');
        Schema::dropIfExists('platform_roles');
    }
};
