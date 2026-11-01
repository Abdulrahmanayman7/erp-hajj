<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_category_coverages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('supplier_category_id')->constrained('supplier_categories')->restrictOnDelete();
            $table->foreignId('geo_place_id')->constrained('geo_places')->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'supplier_id', 'supplier_category_id', 'geo_place_id'],
                'supplier_category_coverages_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_category_coverages');
    }
};
