<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * First tenant-owned table — proves the full scoping stack.
 * UNIQUE (tenant_id, key): a key exists once per tenant; the same key
 * may exist in every tenant. The unique index doubles as the
 * tenant-leading index (docs/03-database/DATABASE_PRINCIPLES.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('key', 100);
            $table->json('value');
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
    }
};
