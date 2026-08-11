<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add assets.current_custody_id FK after asset_custodies exists.
 *
 * @see docs/09-modules/11-assets-and-custodies/DATA_MODEL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->foreign('current_custody_id')
                ->references('id')
                ->on('asset_custodies')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropForeign(['current_custody_id']);
        });
    }
};
