<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Production-safe reconciliation for `transport_run_passengers` after the
 * create migration (2026_10_05_200012) was edited in commit 592f37e.
 *
 * Production (batch 2) still has the complete pre-edit shape:
 * - `required_vehicle_class_at_assignment` / `required_service_level_at_assignment`
 * - `season_vehicle_id_at_assignment` NOT NULL
 * - composite FK `tr_passengers_assigned_sv_fk` on
 *   `(season_id, season_vehicle_id_at_assignment)` → transport_season_vehicles
 * - no `overridden_by_at_assignment` / no `tr_passengers_overridden_at_asn_fk`
 *
 * Target shape (current create migration / DATA_MODEL):
 * - `entitlement_vehicle_class` / `entitlement_service_level` (rename; same snapshot meaning)
 * - `season_vehicle_id_at_assignment` NULL, no season-vehicle FK
 * - `overridden_by_at_assignment` NULL + FK `tr_passengers_overridden_at_asn_fk` → users(id) RESTRICT
 *
 * State machine (fail-closed):
 * - **target** → no-op (fresh DBs from the edited create migration).
 * - **legacy** → apply the forward steps below (complete production shape only).
 * - **partial** → throw; do not auto-heal. Intermediate shapes after a MySQL DDL
 *   failure (each ALTER implicitly commits) are intentionally rejected on re-run.
 *   Recovery is a manual DBA decision (restore from backup, or finish the remaining
 *   steps by hand after inspection). This migration never claims automatic recovery.
 *
 * Override-author backfill:
 * - Legacy docs stated assignment-time override was authorized by `assigned_by`
 *   (no separate `overridden_by_at_assignment` column existed).
 * - When `override_at_assignment = 1` and `assigned_by` is not null, copy into
 *   `overridden_by_at_assignment` — that is the only guaranteed assignment-time author.
 * - Otherwise leave NULL (including override rows with null `assigned_by`).
 * - Live `entitlement_overridden_by` is a different, mutable field and is never copied.
 *
 * Rollback (`down`) is intentionally unsupported for production safety: MySQL DDL
 * cannot be transactionally undone, and reversing after nullable vehicle snapshots
 * or new writes would risk data loss / NOT NULL violations.
 *
 * @see docs/09-modules/17-transportation-and-fleet/DATA_MODEL.md
 */
return new class extends Migration
{
    private const TABLE = 'transport_run_passengers';

    private const LEGACY_CLASS = 'required_vehicle_class_at_assignment';

    private const LEGACY_LEVEL = 'required_service_level_at_assignment';

    private const TARGET_CLASS = 'entitlement_vehicle_class';

    private const TARGET_LEVEL = 'entitlement_service_level';

    private const SEASON_VEHICLE = 'season_vehicle_id_at_assignment';

    private const OVERRIDDEN_BY = 'overridden_by_at_assignment';

    private const ASSIGNED_SV_FK = 'tr_passengers_assigned_sv_fk';

    private const OVERRIDDEN_BY_FK = 'tr_passengers_overridden_at_asn_fk';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            throw new RuntimeException('transport_run_passengers is missing; cannot reconcile entitlement snapshot columns.');
        }

        $state = $this->detectState();

        if ($state === 'target') {
            return;
        }

        if ($state !== 'legacy') {
            throw new RuntimeException(
                'transport_run_passengers entitlement snapshot schema is in an unexpected partial state; '
                .'refusing to continue (no automatic recovery after MySQL DDL failure). Details: '
                .$this->describeState()
            );
        }

        $this->assertMysqlCompatibleDriver();

        // Complete legacy is guaranteed here: FK present, season vehicle NOT NULL.
        // Each step below is a separate implicitly-committed ALTER on InnoDB.
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropForeign(self::ASSIGNED_SV_FK);
        });

        $this->renameColumn(self::LEGACY_CLASS, self::TARGET_CLASS);
        $this->renameColumn(self::LEGACY_LEVEL, self::TARGET_LEVEL);

        DB::statement(
            'ALTER TABLE `'.self::TABLE.'` MODIFY `'.self::SEASON_VEHICLE.'` BIGINT UNSIGNED NULL'
        );

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->unsignedBigInteger(self::OVERRIDDEN_BY)->nullable()->after('override_reason_at_assignment');
        });

        // See class docblock: assignment-time author was only `assigned_by` in legacy.
        DB::table(self::TABLE)
            ->where('override_at_assignment', true)
            ->whereNotNull('assigned_by')
            ->whereNull(self::OVERRIDDEN_BY)
            ->update([self::OVERRIDDEN_BY => DB::raw('`assigned_by`')]);

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->foreign(self::OVERRIDDEN_BY, self::OVERRIDDEN_BY_FK)
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });

        if ($this->detectState() !== 'target') {
            throw new RuntimeException(
                'transport_run_passengers reconciliation finished but schema is still not target. Details: '
                .$this->describeState()
            );
        }
    }

    public function down(): void
    {
        // Intentionally unsupported: production rollback of this DDL is unsafe.
        // Use a verified backup restore if the forward migration must be undone.
        throw new RuntimeException(
            'Rollback of transport_run_passengers entitlement snapshot reconciliation is intentionally disabled. '
            .'Restore from a verified pre-migration backup instead. Current state: '.$this->describeState()
        );
    }

    /**
     * @return 'legacy'|'target'|'partial'
     */
    private function detectState(): string
    {
        if (! Schema::hasColumn(self::TABLE, self::SEASON_VEHICLE)) {
            return 'partial';
        }

        $hasLegacyClass = Schema::hasColumn(self::TABLE, self::LEGACY_CLASS);
        $hasLegacyLevel = Schema::hasColumn(self::TABLE, self::LEGACY_LEVEL);
        $hasTargetClass = Schema::hasColumn(self::TABLE, self::TARGET_CLASS);
        $hasTargetLevel = Schema::hasColumn(self::TABLE, self::TARGET_LEVEL);
        $hasOverriddenBy = Schema::hasColumn(self::TABLE, self::OVERRIDDEN_BY);
        $hasAssignedSvFk = $this->hasForeignKey(self::ASSIGNED_SV_FK);
        $hasOverriddenByFk = $this->hasForeignKey(self::OVERRIDDEN_BY_FK);
        $seasonVehicleNullable = $this->columnIsNullable(self::SEASON_VEHICLE);
        $fkCatalogReliable = in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);

        // Complete production legacy only — missing FK or already-nullable vehicle is partial.
        $isLegacy = $hasLegacyClass && $hasLegacyLevel
            && ! $hasTargetClass && ! $hasTargetLevel
            && ! $hasOverriddenBy
            && ! $hasOverriddenByFk
            && $hasAssignedSvFk
            && ! $seasonVehicleNullable;

        if ($isLegacy) {
            return 'legacy';
        }

        // SQLite's foreign-key catalog often omits named Blueprint constraints;
        // require the MySQL FK name only when the catalog is reliable.
        $isTarget = $hasTargetClass && $hasTargetLevel
            && ! $hasLegacyClass && ! $hasLegacyLevel
            && $hasOverriddenBy
            && ! $hasAssignedSvFk
            && $seasonVehicleNullable
            && ($fkCatalogReliable ? $hasOverriddenByFk : true);

        if ($isTarget) {
            return 'target';
        }

        return 'partial';
    }

    private function describeState(): string
    {
        $flags = [
            'legacy_class' => Schema::hasColumn(self::TABLE, self::LEGACY_CLASS),
            'legacy_level' => Schema::hasColumn(self::TABLE, self::LEGACY_LEVEL),
            'target_class' => Schema::hasColumn(self::TABLE, self::TARGET_CLASS),
            'target_level' => Schema::hasColumn(self::TABLE, self::TARGET_LEVEL),
            'overridden_by' => Schema::hasColumn(self::TABLE, self::OVERRIDDEN_BY),
            'assigned_sv_fk' => $this->hasForeignKey(self::ASSIGNED_SV_FK),
            'overridden_by_fk' => $this->hasForeignKey(self::OVERRIDDEN_BY_FK),
            'season_vehicle_nullable' => Schema::hasColumn(self::TABLE, self::SEASON_VEHICLE)
                ? $this->columnIsNullable(self::SEASON_VEHICLE)
                : null,
            'driver' => Schema::getConnection()->getDriverName(),
        ];

        return json_encode($flags, JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private function hasForeignKey(string $name): bool
    {
        return collect(Schema::getForeignKeys(self::TABLE))
            ->contains(fn (array $fk): bool => ($fk['name'] ?? null) === $name);
    }

    private function columnIsNullable(string $column): bool
    {
        $meta = collect(Schema::getColumns(self::TABLE))->firstWhere('name', $column);
        if ($meta === null) {
            throw new RuntimeException(self::TABLE.'.'.$column.' is missing during reconciliation.');
        }

        return (bool) ($meta['nullable'] ?? false);
    }

    private function renameColumn(string $from, string $to): void
    {
        if (! Schema::hasColumn(self::TABLE, $from)) {
            throw new RuntimeException("Expected column {$from} before rename to {$to}.");
        }
        if (Schema::hasColumn(self::TABLE, $to)) {
            throw new RuntimeException("Target column {$to} already exists while renaming from {$from}.");
        }

        // CHANGE keeps values and works on MySQL 5.7+/MariaDB without RENAME COLUMN.
        DB::statement(
            'ALTER TABLE `'.self::TABLE.'` CHANGE `'.$from.'` `'.$to.'` VARCHAR(32) NULL'
        );
    }

    private function assertMysqlCompatibleDriver(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException(
                "transport_run_passengers reconciliation requires MySQL/MariaDB when applying legacy→target changes; got {$driver}. "
                .'Fresh SQLite/test schemas that already match target are no-ops.'
            );
        }
    }
};
