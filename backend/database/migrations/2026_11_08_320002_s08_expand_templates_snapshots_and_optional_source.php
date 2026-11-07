<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S08 expansion (MATRIX 2026-11-08.1, Owner Option A): independent templates.
 *
 *  - `season_template_items.snapshot_json`: allowlisted field snapshot per kind
 *    (nullable: items without a snapshot keep the live-source behaviour).
 *  - `source_season_id` becomes nullable on previews, runs and provenance so a copy
 *    from a snapshot-complete template needs no live source season.
 *  - `season_copy_row_provenance.natural_key`: the copy key of the target row, so
 *    rows of kinds that are not season-scoped (contract definitions) stay idempotent.
 *
 * Additive/relaxing only. The composite (tenant_id, source_season_id) foreign keys
 * are dropped and recreated around the nullability change on MySQL/MariaDB; SQLite
 * rebuilds the table itself (existing migrations skip FK handling there too).
 * A NULL component disables the composite FK check, so referential integrity is
 * unchanged whenever a source season is present.
 *
 * down() restores NOT NULL and therefore requires that no row has a NULL source.
 *
 * @see docs/09-modules/20-seasonal-templates/DATA_MODEL.md
 */
return new class extends Migration
{
    /** @var array<string, string> table => composite source-season foreign key name */
    private const SOURCE_FKS = [
        'season_copy_previews' => 'sea_cpv_source_season_fk',
        'season_copy_runs' => 'sea_crun_source_season_fk',
        'season_copy_row_provenance' => 'sea_prov_source_season_fk',
    ];

    public function up(): void
    {
        Schema::table('season_template_items', function (Blueprint $table): void {
            $table->json('snapshot_json')->nullable()->after('selection_json');
        });

        Schema::table('season_copy_row_provenance', function (Blueprint $table): void {
            $table->string('natural_key', 255)->nullable()->after('target_row_id');
        });

        foreach (self::SOURCE_FKS as $table => $foreignKey) {
            $this->setSourceNullable($table, $foreignKey, true);
        }
    }

    public function down(): void
    {
        foreach (self::SOURCE_FKS as $table => $foreignKey) {
            $this->setSourceNullable($table, $foreignKey, false);
        }

        Schema::table('season_copy_row_provenance', function (Blueprint $table): void {
            $table->dropColumn('natural_key');
        });

        Schema::table('season_template_items', function (Blueprint $table): void {
            $table->dropColumn('snapshot_json');
        });
    }

    private function setSourceNullable(string $tableName, string $foreignKey, bool $nullable): void
    {
        $managesForeignKeys = in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);

        if ($managesForeignKeys) {
            Schema::table($tableName, function (Blueprint $table) use ($foreignKey): void {
                $table->dropForeign($foreignKey);
            });
        }

        Schema::table($tableName, function (Blueprint $table) use ($nullable): void {
            $table->unsignedBigInteger('source_season_id')->nullable($nullable)->change();
        });

        if ($managesForeignKeys) {
            Schema::table($tableName, function (Blueprint $table) use ($foreignKey): void {
                $table->foreign(['tenant_id', 'source_season_id'], $foreignKey)
                    ->references(['tenant_id', 'id'])
                    ->on('seasons')
                    ->restrictOnDelete();
            });
        }
    }
};
