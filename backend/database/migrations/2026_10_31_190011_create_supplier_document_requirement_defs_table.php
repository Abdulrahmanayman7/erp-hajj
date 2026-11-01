<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_document_requirement_defs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('requirement_set_id')
                ->constrained('supplier_eligibility_requirement_sets')
                ->cascadeOnDelete();
            $table->string('requirement_key', 64);
            $table->string('label_ar', 255);
            $table->text('description')->nullable();
            $table->string('supplier_type', 32);
            $table->char('country_code', 2)->nullable();
            $table->foreignId('supplier_category_id')->nullable()
                ->constrained('supplier_categories')->restrictOnDelete();
            // Application-maintained NULL-safe uniqueness helpers (portable MySQL/SQLite).
            $table->char('country_code_norm', 2)->default('');
            $table->unsignedBigInteger('supplier_category_id_norm')->default(0);
            $table->boolean('is_mandatory')->default(true);
            $table->unsignedInteger('default_validity_days')->nullable();
            $table->timestamps();

            $table->unique(
                [
                    'tenant_id',
                    'requirement_set_id',
                    'requirement_key',
                    'supplier_type',
                    'country_code_norm',
                    'supplier_category_id_norm',
                ],
                'supplier_req_defs_applicability_unique',
            );
            $table->index(['tenant_id', 'requirement_key'], 'supplier_req_defs_tenant_key_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_document_requirement_defs');
    }
};
