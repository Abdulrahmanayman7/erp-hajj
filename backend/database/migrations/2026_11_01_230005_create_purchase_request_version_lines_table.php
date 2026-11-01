<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** S04 Phase III: immutable version lines (III-D7). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_request_version_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('purchase_request_version_id');
            $table->foreign('purchase_request_version_id', 'pr_ver_lines_version_fk')
                ->references('id')
                ->on('purchase_request_versions')
                ->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->text('description');
            $table->text('specifications')->nullable();
            $table->decimal('quantity', 14, 4);
            $table->string('unit', 64);
            $table->date('need_date');
            $table->unsignedBigInteger('inventory_item_id')->nullable();
            $table->decimal('unit_estimate', 14, 2)->nullable();
            $table->decimal('line_estimate', 14, 2)->nullable();
            $table->text('unpriced_reason')->nullable();
            $table->timestamps();

            $table->unique(['purchase_request_version_id', 'line_no'], 'pr_version_lines_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_version_lines');
    }
};
