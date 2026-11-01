<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S04 II-A remediation M3: NULL-safe wildcard uniqueness must not depend on application
 * hooks. On MySQL/MariaDB the normalized columns become STORED generated columns derived
 * from `country_code` / `supplier_category_id` (values recomputed from base columns, so
 * existing rows are preserved). On SQLite (tests) generated columns cannot be added to an
 * existing table, so the physical columns stay and the model layer maintains them.
 */
return new class extends Migration
{
    private const INDEX = 'supplier_req_defs_applicability_unique';

    public function up(): void
    {
        if (! $this->isMySqlFamily()) {
            return;
        }

        Schema::table('supplier_document_requirement_defs', function (Blueprint $table): void {
            $table->dropUnique(self::INDEX);
        });

        Schema::table('supplier_document_requirement_defs', function (Blueprint $table): void {
            $table->dropColumn(['country_code_norm', 'supplier_category_id_norm']);
        });

        Schema::table('supplier_document_requirement_defs', function (Blueprint $table): void {
            $table->char('country_code_norm', 2)->storedAs("IFNULL(`country_code`, '')")->after('supplier_category_id');
            $table->unsignedBigInteger('supplier_category_id_norm')->storedAs('IFNULL(`supplier_category_id`, 0)')->after('country_code_norm');
        });

        $this->addUnique();
    }

    public function down(): void
    {
        if (! $this->isMySqlFamily()) {
            return;
        }

        Schema::table('supplier_document_requirement_defs', function (Blueprint $table): void {
            $table->dropUnique(self::INDEX);
        });

        Schema::table('supplier_document_requirement_defs', function (Blueprint $table): void {
            $table->dropColumn(['country_code_norm', 'supplier_category_id_norm']);
        });

        Schema::table('supplier_document_requirement_defs', function (Blueprint $table): void {
            $table->char('country_code_norm', 2)->default('')->after('supplier_category_id');
            $table->unsignedBigInteger('supplier_category_id_norm')->default(0)->after('country_code_norm');
        });

        DB::table('supplier_document_requirement_defs')->update([
            'country_code_norm' => DB::raw("IFNULL(`country_code`, '')"),
            'supplier_category_id_norm' => DB::raw('IFNULL(`supplier_category_id`, 0)'),
        ]);

        $this->addUnique();
    }

    private function addUnique(): void
    {
        Schema::table('supplier_document_requirement_defs', function (Blueprint $table): void {
            $table->unique(
                ['tenant_id', 'requirement_set_id', 'requirement_key', 'supplier_type', 'country_code_norm', 'supplier_category_id_norm'],
                self::INDEX,
            );
        });
    }

    private function isMySqlFamily(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};
