<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S04 II-A remediation M3 (MariaDB Hostinger hotfix):
 *
 * NULL-safe wildcard uniqueness must not depend on application hooks alone.
 * MariaDB 11.8.x on Hostinger rejects IFNULL / COALESCE / CASE WHEN inside
 * STORED generated columns for these norms, so MySQL-family engines use:
 *   - ordinary physical `*_norm` columns
 *   - BEFORE INSERT / BEFORE UPDATE triggers that always recompute norms from
 *     `country_code` / `supplier_category_id` (NULL → '' / 0)
 *   - UNIQUE index `supplier_req_defs_applicability_unique`
 *
 * SQLite (tests) cannot rely on these triggers in the same way; the model
 * `saving` hook continues to maintain the physical columns there.
 *
 * Idempotent for the partial production failure where the unique index and
 * both norm columns were already dropped but this migration was not recorded.
 */
return new class extends Migration
{
    private const INDEX = 'supplier_req_defs_applicability_unique';

    private const TABLE = 'supplier_document_requirement_defs';

    private const TRIGGER_BI = 'supplier_req_defs_norms_bi';

    private const TRIGGER_BU = 'supplier_req_defs_norms_bu';

    public function up(): void
    {
        if (! $this->isMySqlFamily()) {
            return;
        }

        $this->dropUniqueIfExists();
        $this->dropTriggersIfExist();
        $this->ensurePhysicalNormColumns();
        $this->backfillNorms();
        $this->createNormTriggers();
        $this->addUniqueIfMissing();
    }

    public function down(): void
    {
        if (! $this->isMySqlFamily()) {
            return;
        }

        // Restore the 190011 physical-column shape (app-maintained norms).
        $this->dropTriggersIfExist();
        $this->backfillNorms();
        $this->addUniqueIfMissing();
    }

    private function ensurePhysicalNormColumns(): void
    {
        $hasCountryNorm = Schema::hasColumn(self::TABLE, 'country_code_norm');
        $hasCategoryNorm = Schema::hasColumn(self::TABLE, 'supplier_category_id_norm');

        // Drop any existing norm columns (STORED generated or physical leftover)
        // so the resulting shape is always ordinary columns with defaults.
        if ($hasCountryNorm || $hasCategoryNorm) {
            Schema::table(self::TABLE, function (Blueprint $table) use ($hasCountryNorm, $hasCategoryNorm): void {
                $drop = [];
                if ($hasCountryNorm) {
                    $drop[] = 'country_code_norm';
                }
                if ($hasCategoryNorm) {
                    $drop[] = 'supplier_category_id_norm';
                }
                $table->dropColumn($drop);
            });
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->char('country_code_norm', 2)->default('')->after('supplier_category_id');
            $table->unsignedBigInteger('supplier_category_id_norm')->default(0)->after('country_code_norm');
        });
    }

    private function backfillNorms(): void
    {
        if (! Schema::hasColumn(self::TABLE, 'country_code_norm')
            || ! Schema::hasColumn(self::TABLE, 'supplier_category_id_norm')) {
            return;
        }

        DB::table(self::TABLE)->update([
            'country_code_norm' => DB::raw("IFNULL(`country_code`, '')"),
            'supplier_category_id_norm' => DB::raw('IFNULL(`supplier_category_id`, 0)'),
        ]);
    }

    private function createNormTriggers(): void
    {
        // Single-assignment SET form avoids DELIMITER / BEGIN…END issues via PDO.
        DB::unprepared(
            'CREATE TRIGGER `'.self::TRIGGER_BI.'`
            BEFORE INSERT ON `'.self::TABLE.'`
            FOR EACH ROW
            SET NEW.`country_code_norm` = IFNULL(NEW.`country_code`, \'\'),
                NEW.`supplier_category_id_norm` = IFNULL(NEW.`supplier_category_id`, 0)'
        );

        DB::unprepared(
            'CREATE TRIGGER `'.self::TRIGGER_BU.'`
            BEFORE UPDATE ON `'.self::TABLE.'`
            FOR EACH ROW
            SET NEW.`country_code_norm` = IFNULL(NEW.`country_code`, \'\'),
                NEW.`supplier_category_id_norm` = IFNULL(NEW.`supplier_category_id`, 0)'
        );
    }

    private function dropTriggersIfExist(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS `'.self::TRIGGER_BI.'`');
        DB::unprepared('DROP TRIGGER IF EXISTS `'.self::TRIGGER_BU.'`');
    }

    private function dropUniqueIfExists(): void
    {
        if (! $this->hasUniqueIndex()) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropUnique(self::INDEX);
        });
    }

    private function addUniqueIfMissing(): void
    {
        if ($this->hasUniqueIndex()) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->unique(
                [
                    'tenant_id',
                    'requirement_set_id',
                    'requirement_key',
                    'supplier_type',
                    'country_code_norm',
                    'supplier_category_id_norm',
                ],
                self::INDEX,
            );
        });
    }

    private function hasUniqueIndex(): bool
    {
        foreach (Schema::getIndexes(self::TABLE) as $index) {
            if (($index['name'] ?? null) === self::INDEX) {
                return true;
            }
        }

        return false;
    }

    private function isMySqlFamily(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};
