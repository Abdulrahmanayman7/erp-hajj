<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MySQL/SQLite-compatible partial uniqueness: at most one primary contact per supplier.
 * Mirrors season_pilgrims.family_primary_flag pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('supplier_contacts')
            ->select('supplier_id', DB::raw('COUNT(*) as c'))
            ->where('is_primary_contact', true)
            ->groupBy('supplier_id')
            ->having('c', '>', 1)
            ->get();

        if ($duplicates->isNotEmpty()) {
            throw new \RuntimeException(
                'Cannot add supplier_contacts primary uniqueness: duplicate primary contacts exist for supplier_id(s): '
                .$duplicates->pluck('supplier_id')->implode(', '),
            );
        }

        Schema::table('supplier_contacts', function (Blueprint $table): void {
            $table->unsignedTinyInteger('primary_flag')
                ->nullable()
                ->storedAs('case when is_primary_contact = 1 then 1 else null end');

            $table->unique(['supplier_id', 'primary_flag'], 'supplier_contacts_one_primary_unique');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_contacts', function (Blueprint $table): void {
            $table->dropUnique('supplier_contacts_one_primary_unique');
            $table->dropColumn('primary_flag');
        });
    }
};
