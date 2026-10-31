<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @return array{
 *   legacy_class: bool,
 *   legacy_level: bool,
 *   target_class: bool,
 *   target_level: bool,
 *   overridden_by: bool,
 *   assigned_sv_fk: bool,
 *   overridden_by_fk: bool,
 *   season_vehicle_nullable: bool|null
 * }
 */
function trPassengerSnapshotFlags(): array
{
    $fks = collect(Schema::getForeignKeys('transport_run_passengers'))->pluck('name')->all();
    $seasonVehicle = collect(Schema::getColumns('transport_run_passengers'))
        ->firstWhere('name', 'season_vehicle_id_at_assignment');

    return [
        'legacy_class' => Schema::hasColumn('transport_run_passengers', 'required_vehicle_class_at_assignment'),
        'legacy_level' => Schema::hasColumn('transport_run_passengers', 'required_service_level_at_assignment'),
        'target_class' => Schema::hasColumn('transport_run_passengers', 'entitlement_vehicle_class'),
        'target_level' => Schema::hasColumn('transport_run_passengers', 'entitlement_service_level'),
        'overridden_by' => Schema::hasColumn('transport_run_passengers', 'overridden_by_at_assignment'),
        'assigned_sv_fk' => in_array('tr_passengers_assigned_sv_fk', $fks, true),
        'overridden_by_fk' => in_array('tr_passengers_overridden_at_asn_fk', $fks, true),
        'season_vehicle_nullable' => $seasonVehicle === null ? null : (bool) ($seasonVehicle['nullable'] ?? false),
    ];
}

function trAssertPassengerTargetSchema(): void
{
    $flags = trPassengerSnapshotFlags();
    $expected = [
        'legacy_class' => false,
        'legacy_level' => false,
        'target_class' => true,
        'target_level' => true,
        'overridden_by' => true,
        'assigned_sv_fk' => false,
        'season_vehicle_nullable' => true,
    ];

    if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $expected['overridden_by_fk'] = true;
    }

    expect($flags)->toMatchArray($expected);
}

function trLoadPassengerReconciliationMigration(): object
{
    return require database_path('migrations/2026_10_31_120000_reconcile_transport_run_passengers_entitlement_snapshot.php');
}

/**
 * Downgrade a target schema row set to the complete production legacy shape.
 */
function trDowngradePassengersToLegacyMysql(): void
{
    if (in_array('tr_passengers_overridden_at_asn_fk', collect(Schema::getForeignKeys('transport_run_passengers'))->pluck('name')->all(), true)) {
        Schema::table('transport_run_passengers', function ($table): void {
            $table->dropForeign('tr_passengers_overridden_at_asn_fk');
        });
    }

    if (Schema::hasColumn('transport_run_passengers', 'overridden_by_at_assignment')) {
        Schema::table('transport_run_passengers', function ($table): void {
            $table->dropColumn('overridden_by_at_assignment');
        });
    }

    $nullVehicles = (int) DB::table('transport_run_passengers')->whereNull('season_vehicle_id_at_assignment')->count();
    expect($nullVehicles)->toBe(0, 'legacy NOT NULL requires every season_vehicle_id_at_assignment to be set');

    DB::statement('ALTER TABLE `transport_run_passengers` MODIFY `season_vehicle_id_at_assignment` BIGINT UNSIGNED NOT NULL');
    DB::statement('ALTER TABLE `transport_run_passengers` CHANGE `entitlement_vehicle_class` `required_vehicle_class_at_assignment` VARCHAR(32) NULL');
    DB::statement('ALTER TABLE `transport_run_passengers` CHANGE `entitlement_service_level` `required_service_level_at_assignment` VARCHAR(32) NULL');

    Schema::table('transport_run_passengers', function ($table): void {
        $table->foreign(['season_id', 'season_vehicle_id_at_assignment'], 'tr_passengers_assigned_sv_fk')
            ->references(['season_id', 'id'])
            ->on('transport_season_vehicles')
            ->restrictOnDelete();
    });
}

/**
 * Restore a known partial/broken shape back to target so later tests stay clean.
 */
function trRestorePassengersToTargetMysqlFromPartialMixedNames(): void
{
    if (Schema::hasColumn('transport_run_passengers', 'required_service_level_at_assignment')
        && ! Schema::hasColumn('transport_run_passengers', 'entitlement_service_level')) {
        DB::statement('ALTER TABLE `transport_run_passengers` CHANGE `required_service_level_at_assignment` `entitlement_service_level` VARCHAR(32) NULL');
    }
    if (Schema::hasColumn('transport_run_passengers', 'required_vehicle_class_at_assignment')
        && ! Schema::hasColumn('transport_run_passengers', 'entitlement_vehicle_class')) {
        DB::statement('ALTER TABLE `transport_run_passengers` CHANGE `required_vehicle_class_at_assignment` `entitlement_vehicle_class` VARCHAR(32) NULL');
    }

    if (! Schema::hasColumn('transport_run_passengers', 'overridden_by_at_assignment')) {
        Schema::table('transport_run_passengers', function ($table): void {
            $table->unsignedBigInteger('overridden_by_at_assignment')->nullable()->after('override_reason_at_assignment');
        });
    }

    $fks = collect(Schema::getForeignKeys('transport_run_passengers'))->pluck('name')->all();
    if (in_array('tr_passengers_assigned_sv_fk', $fks, true)) {
        Schema::table('transport_run_passengers', function ($table): void {
            $table->dropForeign('tr_passengers_assigned_sv_fk');
        });
    }

    DB::statement('ALTER TABLE `transport_run_passengers` MODIFY `season_vehicle_id_at_assignment` BIGINT UNSIGNED NULL');

    if (! in_array('tr_passengers_overridden_at_asn_fk', $fks, true)
        && Schema::hasColumn('transport_run_passengers', 'overridden_by_at_assignment')) {
        Schema::table('transport_run_passengers', function ($table): void {
            $table->foreign('overridden_by_at_assignment', 'tr_passengers_overridden_at_asn_fk')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });
    }

    trAssertPassengerTargetSchema();
}

it('exposes the reconciled entitlement snapshot columns and FKs after migrate', function (): void {
    trAssertPassengerTargetSchema();
});

it('is a no-op when the create migration already produced the target schema', function (): void {
    trAssertPassengerTargetSchema();

    $before = trPassengerSnapshotFlags();
    trLoadPassengerReconciliationMigration()->up();

    expect(trPassengerSnapshotFlags())->toBe($before);
    trAssertPassengerTargetSchema();
});

it('reconciles a complete legacy passenger schema while preserving snapshot values and FK rules on mysql', function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('Legacy→target DDL reconciliation is MySQL/MariaDB-specific.');
    }

    $owner = actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'sv' => $sv, 'header' => $h] = trScenario(10, 'premium');
    $vip = spCreateProgram($season['id'], 'VIP');
    spaPostJson('/api/v1/transport-entitlements', [
        'season_program_id' => $vip['id'],
        'vehicle_class' => 'bus',
        'service_level' => 'premium',
    ], $h)->assertCreated();

    [$compatibleId] = trAddPassengers($run['id'], [trActiveSp($season['id'], $vip['id'])['id']], $h);

    $mismatchProgram = spCreateProgram($season['id'], 'ECON');
    spaPostJson('/api/v1/transport-entitlements', [
        'season_program_id' => $mismatchProgram['id'],
        'vehicle_class' => 'minibus',
        'service_level' => 'standard',
    ], $h)->assertCreated();
    $mismatchSp = trActiveSp($season['id'], $mismatchProgram['id']);
    spaPostJson("/api/v1/transport-runs/{$run['id']}/passengers", [
        'source' => 'individual',
        'season_pilgrim_ids' => [$mismatchSp['id']],
        'entitlement_override' => true,
        'override_reason' => 'قبول الاستثناء قبل التسوية',
    ], $h)->assertCreated();
    $overrideId = (int) collect(spaGetJson("/api/v1/transport-runs/{$run['id']}/passengers", $h)->assertOk()->json('data'))
        ->firstWhere('season_pilgrim_id', $mismatchSp['id'])['id'];

    $beforeCompatible = (array) DB::table('transport_run_passengers')->where('id', $compatibleId)->first();
    $beforeOverride = (array) DB::table('transport_run_passengers')->where('id', $overrideId)->first();

    expect($beforeCompatible['entitlement_vehicle_class'])->toBe('bus')
        ->and($beforeCompatible['entitlement_service_level'])->toBe('premium')
        ->and($beforeCompatible['season_vehicle_id_at_assignment'])->toBe($sv['id'])
        ->and((bool) $beforeOverride['override_at_assignment'])->toBeTrue()
        ->and($beforeOverride['assigned_by'])->toBe($owner->id);

    trDowngradePassengersToLegacyMysql();

    // Simulate legacy assignment-time override with null assigned_by (no guaranteed author).
    DB::table('transport_run_passengers')->where('id', $compatibleId)->update([
        'override_at_assignment' => true,
        'assigned_by' => null,
        'override_reason_at_assignment' => 'legacy row without assigned_by',
    ]);

    $legacyCompatible = (array) DB::table('transport_run_passengers')->where('id', $compatibleId)->first();
    $legacyOverride = (array) DB::table('transport_run_passengers')->where('id', $overrideId)->first();
    $legacyFlags = trPassengerSnapshotFlags();

    expect($legacyFlags)->toMatchArray([
        'legacy_class' => true,
        'legacy_level' => true,
        'target_class' => false,
        'target_level' => false,
        'overridden_by' => false,
        'assigned_sv_fk' => true,
        'overridden_by_fk' => false,
        'season_vehicle_nullable' => false,
    ])
        ->and($legacyCompatible['required_vehicle_class_at_assignment'])->toBe('bus')
        ->and($legacyCompatible['required_service_level_at_assignment'])->toBe('premium')
        ->and($legacyCompatible['season_vehicle_id_at_assignment'])->toBe($sv['id'])
        ->and($legacyOverride['required_vehicle_class_at_assignment'])->toBe('minibus')
        ->and($legacyOverride['assigned_by'])->toBe($owner->id)
        ->and($legacyOverride)->not->toHaveKey('overridden_by_at_assignment');

    trLoadPassengerReconciliationMigration()->up();

    trAssertPassengerTargetSchema();

    $afterCompatible = (array) DB::table('transport_run_passengers')->where('id', $compatibleId)->first();
    $afterOverride = (array) DB::table('transport_run_passengers')->where('id', $overrideId)->first();

    expect($afterCompatible['entitlement_vehicle_class'])->toBe('bus')
        ->and($afterCompatible['entitlement_service_level'])->toBe('premium')
        ->and($afterCompatible['season_vehicle_id_at_assignment'])->toBe($sv['id'])
        ->and($afterCompatible['vehicle_class_at_assignment'])->toBe($beforeCompatible['vehicle_class_at_assignment'])
        ->and($afterCompatible['service_level_at_assignment'])->toBe($beforeCompatible['service_level_at_assignment'])
        ->and($afterCompatible['entitlement_id_at_assignment'])->toBe($beforeCompatible['entitlement_id_at_assignment'])
        ->and($afterCompatible['program_id_at_assignment'])->toBe($beforeCompatible['program_id_at_assignment'])
        // No guaranteed assignment-time author when assigned_by was null → stay NULL.
        ->and($afterCompatible['overridden_by_at_assignment'])->toBeNull()
        ->and($afterOverride['entitlement_vehicle_class'])->toBe('minibus')
        ->and($afterOverride['entitlement_service_level'])->toBe('standard')
        ->and((bool) $afterOverride['override_at_assignment'])->toBeTrue()
        // Legacy docs: assignment-time override authorized by assigned_by.
        ->and($afterOverride['overridden_by_at_assignment'])->toBe($owner->id)
        ->and($afterOverride['assigned_by'])->toBe($owner->id)
        ->and(DB::table('transport_run_passengers')->count())->toBe(2);

    // Nullable season vehicle snapshot is allowed after FK drop.
    DB::table('transport_run_passengers')->where('id', $compatibleId)->update([
        'season_vehicle_id_at_assignment' => null,
    ]);
    expect(DB::table('transport_run_passengers')->where('id', $compatibleId)->value('season_vehicle_id_at_assignment'))
        ->toBeNull();

    // overridden_by_at_assignment FK is RESTRICT on users.
    expect(fn () => DB::table('users')->where('id', $owner->id)->delete())
        ->toThrow(QueryException::class);
});

it('refuses mixed-column partial schemas after a simulated MySQL DDL failure', function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('Partial-state detection uses MySQL DDL.');
    }

    trAssertPassengerTargetSchema();

    // Mid-rename failure shape: one entitlement column renamed back to legacy.
    DB::statement('ALTER TABLE `transport_run_passengers` CHANGE `entitlement_service_level` `required_service_level_at_assignment` VARCHAR(32) NULL');
    $partial = trPassengerSnapshotFlags();

    expect($partial['target_class'])->toBeTrue()
        ->and($partial['legacy_level'])->toBeTrue()
        ->and($partial['target_level'])->toBeFalse();

    $migration = trLoadPassengerReconciliationMigration();
    expect(fn () => $migration->up())
        ->toThrow(RuntimeException::class, 'unexpected partial state');

    // Re-run still refuses — no automatic recovery.
    expect(fn () => $migration->up())
        ->toThrow(RuntimeException::class, 'no automatic recovery');

    expect(trPassengerSnapshotFlags())->toMatchArray([
        'target_class' => true,
        'legacy_level' => true,
        'target_level' => false,
    ]);

    trRestorePassengersToTargetMysqlFromPartialMixedNames();
});

it('refuses incomplete legacy shapes missing the composite season-vehicle FK', function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('Partial-state detection uses MySQL DDL.');
    }

    actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'header' => $h] = trScenario(10, 'premium');
    $vip = spCreateProgram($season['id'], 'VIP');
    spaPostJson('/api/v1/transport-entitlements', [
        'season_program_id' => $vip['id'],
        'vehicle_class' => 'bus',
        'service_level' => 'premium',
    ], $h)->assertCreated();
    [$passengerId] = trAddPassengers($run['id'], [trActiveSp($season['id'], $vip['id'])['id']], $h);
    expect($passengerId)->toBeGreaterThan(0);

    trDowngradePassengersToLegacyMysql();
    expect(trPassengerSnapshotFlags()['assigned_sv_fk'])->toBeTrue()
        ->and(trPassengerSnapshotFlags()['season_vehicle_nullable'])->toBeFalse();

    // Simulate DDL failure after FK drop but before renames.
    Schema::table('transport_run_passengers', function ($table): void {
        $table->dropForeign('tr_passengers_assigned_sv_fk');
    });

    $flags = trPassengerSnapshotFlags();
    expect($flags)->toMatchArray([
        'legacy_class' => true,
        'legacy_level' => true,
        'target_class' => false,
        'target_level' => false,
        'overridden_by' => false,
        'assigned_sv_fk' => false,
        'season_vehicle_nullable' => false,
    ]);

    $migration = trLoadPassengerReconciliationMigration();
    expect(fn () => $migration->up())
        ->toThrow(RuntimeException::class, 'unexpected partial state');

    // Still incomplete legacy — not auto-completed.
    expect(trPassengerSnapshotFlags()['assigned_sv_fk'])->toBeFalse()
        ->and(Schema::hasColumn('transport_run_passengers', 'required_vehicle_class_at_assignment'))->toBeTrue();

    // Restore complete legacy FK then forward-migrate so the suite ends on target.
    Schema::table('transport_run_passengers', function ($table): void {
        $table->foreign(['season_id', 'season_vehicle_id_at_assignment'], 'tr_passengers_assigned_sv_fk')
            ->references(['season_id', 'id'])
            ->on('transport_season_vehicles')
            ->restrictOnDelete();
    });
    $migration->up();
    trAssertPassengerTargetSchema();
});

it('refuses incomplete legacy shapes when season_vehicle is already nullable', function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('Partial-state detection uses MySQL DDL.');
    }

    actingAsTenantOwner();
    ['run' => $run, 'season' => $season, 'header' => $h] = trScenario(10, 'standard');
    [$passengerId] = trAddPassengers($run['id'], [trActiveSp($season['id'])['id']], $h);
    expect($passengerId)->toBeGreaterThan(0);

    trDowngradePassengersToLegacyMysql();

    Schema::table('transport_run_passengers', function ($table): void {
        $table->dropForeign('tr_passengers_assigned_sv_fk');
    });
    DB::statement('ALTER TABLE `transport_run_passengers` MODIFY `season_vehicle_id_at_assignment` BIGINT UNSIGNED NULL');
    Schema::table('transport_run_passengers', function ($table): void {
        $table->foreign(['season_id', 'season_vehicle_id_at_assignment'], 'tr_passengers_assigned_sv_fk')
            ->references(['season_id', 'id'])
            ->on('transport_season_vehicles')
            ->restrictOnDelete();
    });

    expect(trPassengerSnapshotFlags())->toMatchArray([
        'legacy_class' => true,
        'legacy_level' => true,
        'assigned_sv_fk' => true,
        'season_vehicle_nullable' => true,
        'target_class' => false,
        'overridden_by' => false,
    ]);

    expect(fn () => trLoadPassengerReconciliationMigration()->up())
        ->toThrow(RuntimeException::class, 'unexpected partial state');

    // Manual restore path for suite hygiene: drop FK, rename, add override column/FK.
    Schema::table('transport_run_passengers', function ($table): void {
        $table->dropForeign('tr_passengers_assigned_sv_fk');
    });
    DB::statement('ALTER TABLE `transport_run_passengers` CHANGE `required_vehicle_class_at_assignment` `entitlement_vehicle_class` VARCHAR(32) NULL');
    DB::statement('ALTER TABLE `transport_run_passengers` CHANGE `required_service_level_at_assignment` `entitlement_service_level` VARCHAR(32) NULL');
    Schema::table('transport_run_passengers', function ($table): void {
        $table->unsignedBigInteger('overridden_by_at_assignment')->nullable()->after('override_reason_at_assignment');
        $table->foreign('overridden_by_at_assignment', 'tr_passengers_overridden_at_asn_fk')
            ->references('id')
            ->on('users')
            ->restrictOnDelete();
    });
    trAssertPassengerTargetSchema();
});

it('disables migrate:rollback for this reconciliation on mysql', function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('Rollback refusal is asserted on MySQL/MariaDB.');
    }

    trAssertPassengerTargetSchema();
    $before = trPassengerSnapshotFlags();
    $rowCount = (int) DB::table('transport_run_passengers')->count();

    $migration = trLoadPassengerReconciliationMigration();
    expect(fn () => $migration->down())
        ->toThrow(RuntimeException::class, 'intentionally disabled');

    // No destructive DDL ran: schema and row count unchanged.
    expect(trPassengerSnapshotFlags())->toBe($before)
        ->and((int) DB::table('transport_run_passengers')->count())->toBe($rowCount);
    trAssertPassengerTargetSchema();
});
