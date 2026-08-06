<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform table — the tenant registry itself (no tenant_id by definition).
 * Schema per docs/09-modules/00-tenancy/DATA_MODEL.md.
 * No soft deletes: "archived" is the explicit lifecycle mechanism.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_code', 63)->unique();
            $table->string('name')->unique();
            $table->string('status', 20)->default('pending')->index();
            $table->string('locale', 10)->default('ar');
            $table->string('timezone', 64)->default('Asia/Riyadh');
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
