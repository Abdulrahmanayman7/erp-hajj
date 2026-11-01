<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_eligibility_requirement_sets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('config_key', 64);
            $table->string('name', 150);
            $table->string('status', 16)->default('draft');
            $table->unsignedInteger('version_no');
            // Application-maintained: 0 while draft, version_no once approved.
            // Unique (tenant, config_key, draft_slot) => at most one draft per config_key.
            $table->unsignedInteger('draft_slot')->default(0);
            $table->foreignId('authored_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('last_edited_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'config_key', 'version_no'], 'supplier_elig_sets_version_unique');
            $table->unique(['tenant_id', 'config_key', 'draft_slot'], 'supplier_elig_sets_one_draft_unique');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_eligibility_requirement_sets');
    }
};
