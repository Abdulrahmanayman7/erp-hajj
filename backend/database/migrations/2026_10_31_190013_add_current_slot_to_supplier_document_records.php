<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S04 II-A remediation M2: at most ONE non-superseded ("current") record per
 * (tenant, supplier, requirement_key), enforced by the database.
 *
 * - `requirement_key` is denormalized from the definition (requirement_def_id
 *   may differ across set versions for the same key; the key is the identity).
 * - `current_slot` is 0 for the current row and the row id for superseded rows,
 *   so UNIQUE(tenant_id, supplier_id, requirement_key, current_slot) admits a
 *   single current row plus unlimited history. Maintained by the model layer.
 *
 * Additive. Aborts (no silent cleanup) if duplicate current rows already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('supplier_document_records as r')
            ->join('supplier_document_requirement_defs as d', 'd.id', '=', 'r.requirement_def_id')
            ->where('r.verification_status', '!=', 'superseded')
            ->groupBy('r.tenant_id', 'r.supplier_id', 'd.requirement_key')
            ->havingRaw('count(*) > 1')
            ->select('r.tenant_id', 'r.supplier_id', 'd.requirement_key')
            ->get();

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Duplicate current supplier document records exist; resolve manually before migrating: '
                .$duplicates->toJson(),
            );
        }

        Schema::table('supplier_document_records', function (Blueprint $table): void {
            // Temporary default only so the column can be added to a populated table;
            // the model layer always sets the real value on create.
            $table->string('requirement_key', 64)->default('')->after('requirement_def_id');
            $table->unsignedBigInteger('current_slot')->default(0)->after('supersedes_id');
        });

        DB::table('supplier_document_records')->orderBy('id')->each(function (object $row): void {
            $key = DB::table('supplier_document_requirement_defs')->where('id', $row->requirement_def_id)->value('requirement_key');

            DB::table('supplier_document_records')->where('id', $row->id)->update([
                'requirement_key' => (string) $key,
                'current_slot' => $row->verification_status === 'superseded' ? $row->id : 0,
            ]);
        });

        Schema::table('supplier_document_records', function (Blueprint $table): void {
            $table->unique(
                ['tenant_id', 'supplier_id', 'requirement_key', 'current_slot'],
                'supplier_doc_records_one_current_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('supplier_document_records', function (Blueprint $table): void {
            $table->dropUnique('supplier_doc_records_one_current_unique');
        });

        Schema::table('supplier_document_records', function (Blueprint $table): void {
            $table->dropColumn(['requirement_key', 'current_slot']);
        });
    }
};
