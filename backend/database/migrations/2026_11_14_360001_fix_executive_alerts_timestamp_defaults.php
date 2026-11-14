<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Forward-only repair for environments that already ran
 * 2026_11_10_330001_create_executive_alerts_table before useCurrent() was added.
 *
 * Fresh installs already get DEFAULT CURRENT_TIMESTAMP from the create migration.
 * Upgrade path (MySQL/MariaDB): ensure opened_at / last_seen_at have explicit defaults
 * so NO_ZERO_DATE does not leave an implicit zero-date default on the second TIMESTAMP.
 *
 * SQLite: no-op (create path + RefreshDatabase already recreate tables).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('executive_alerts')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        // Idempotent: re-apply safe defaults even if already CURRENT_TIMESTAMP.
        DB::statement(
            'ALTER TABLE `executive_alerts`
                MODIFY `opened_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                MODIFY `last_seen_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
        );
    }

    public function down(): void
    {
        // Non-destructive: leave explicit defaults in place (safe for both create variants).
    }
};
