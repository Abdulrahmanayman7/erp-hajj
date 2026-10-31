<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform geographic reference catalog (S04 Phase I).
 *
 * @see docs/09-modules/19-suppliers-and-procurement/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_places', function (Blueprint $table): void {
            $table->id();
            $table->char('country_code', 2);
            $table->string('kind', 16);
            $table->string('code', 32);
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->foreignId('parent_id')->nullable()->constrained('geo_places')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['country_code', 'kind', 'code'], 'geo_places_country_kind_code_unique');
            $table->index(['country_code', 'kind', 'is_active']);
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_places');
    }
};
