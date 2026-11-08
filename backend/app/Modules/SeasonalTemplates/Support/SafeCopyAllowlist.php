<?php

namespace App\Modules\SeasonalTemplates\Support;

use App\Modules\SeasonalTemplates\Exceptions\SeasonalTemplatesDomainException;

/**
 * Deny-by-default allowlist of copyable entity kinds (S08-D01).
 *
 * Only the kinds and fields below may ever be copied. Anything not listed —
 * pilgrims/PII, risks, complaints, inventory movements, assignments, execution
 * history, audit logs, staff or supplier contacts — is NOT AUTHORIZED FOR COPYING.
 * Adding a kind or field here requires an Owner decision and a MATRIX_VERSION bump.
 *
 * @see docs/09-modules/20-seasonal-templates/SAFE_COPY_ENTITY_MATRIX.md
 */
final class SafeCopyAllowlist
{
    public const MATRIX_VERSION = '2026-11-08.2';

    public const SEASON_PROGRAMS = 'season_programs';

    public const SEASON_PILGRIM_GROUPS = 'season_pilgrim_groups';

    public const TRANSPORT_ENTITLEMENTS = 'transport_entitlements';

    public const TRANSPORT_SEASON_VEHICLES = 'transport_season_vehicles';

    public const ACCOMMODATION_SEASON_FACILITIES = 'accommodation_season_facilities';

    /** Snapshot-only: contracts are not season-scoped, so there is no live source (X02). */
    public const CONTRACT_DEFINITIONS = 'contract_definitions';

    public const SEASON_PROJECTS = 'season_projects';

    public const SEASON_PROJECT_PHASES = 'season_project_phases';

    public const TASK_DEFINITIONS = 'task_definitions';

    /** S08-D16: tenant/season scoped reminder configuration; copies always land as draft. */
    public const SEASON_REMINDER_RULES = 'season_reminder_rules';

    /**
     * Approved kinds in dependency order (programs before their entitlements).
     * Each entry: natural-key field + the only fields an adapter may carry
     * (+ the subset of those fields remapped by the season date strategy).
     *
     * For the S08 expansion kinds code is a template-local copy key (never a business
     * field) and project_code is the structural link to a season_projects row.
     *
     * @var array<string, array{key: string, fields: list<string>, dates?: list<string>}>
     */
    private const MATRIX = [
        self::SEASON_PROGRAMS => [
            'key' => 'code',
            'fields' => ['code', 'name', 'description', 'is_active'],
        ],
        self::SEASON_PILGRIM_GROUPS => [
            'key' => 'code',
            'fields' => ['code', 'name', 'notes'],
        ],
        self::TRANSPORT_ENTITLEMENTS => [
            'key' => 'program_code',
            'fields' => ['program_code', 'vehicle_class', 'service_level', 'notes'],
        ],
        self::TRANSPORT_SEASON_VEHICLES => [
            'key' => 'plate_number',
            'fields' => ['plate_number', 'operational_capacity', 'service_level', 'is_active', 'notes'],
        ],
        self::ACCOMMODATION_SEASON_FACILITIES => [
            'key' => 'facility_code',
            'fields' => ['facility_code', 'audience', 'is_active'],
        ],
        self::CONTRACT_DEFINITIONS => [
            'key' => 'code',
            'fields' => ['code', 'title', 'contract_category_id', 'counterparty_kind', 'notes', 'currency', 'start_date', 'end_date'],
            'dates' => ['start_date', 'end_date'],
        ],
        self::SEASON_PROJECTS => [
            'key' => 'code',
            'fields' => ['code', 'title', 'description'],
        ],
        self::SEASON_PROJECT_PHASES => [
            'key' => 'code',
            'fields' => ['code', 'project_code', 'title', 'sort_order', 'starts_on', 'ends_on'],
            'dates' => ['starts_on', 'ends_on'],
        ],
        self::TASK_DEFINITIONS => [
            'key' => 'code',
            'fields' => ['code', 'project_code', 'title', 'description', 'notes', 'priority', 'start_date', 'due_date', 'organization_unit_id'],
            'dates' => ['start_date', 'due_date'],
        ],
        self::SEASON_REMINDER_RULES => [
            'key' => 'code',
            'fields' => ['code', 'title', 'body', 'severity', 'trigger_mode', 'trigger_on', 'offset_days', 'recipient_mode', 'recipient_permission'],
            'dates' => ['trigger_on'],
        ],
    ];

    /**
     * @return list<string>
     */
    public static function kinds(): array
    {
        return array_keys(self::MATRIX);
    }

    public static function isApproved(string $kind): bool
    {
        return array_key_exists($kind, self::MATRIX);
    }

    /**
     * @return list<string>
     */
    public static function fieldsFor(string $kind): array
    {
        self::assertApproved($kind);

        return self::MATRIX[$kind]['fields'];
    }

    /**
     * @return list<string> fields remapped by the date strategy (empty when none)
     */
    public static function dateFieldsFor(string $kind): array
    {
        self::assertApproved($kind);

        return self::MATRIX[$kind]['dates'] ?? [];
    }

    /**
     * Kinds with no live season source: they exist only as template snapshots.
     */
    public static function isSnapshotOnly(string $kind): bool
    {
        self::assertApproved($kind);

        return $kind === self::CONTRACT_DEFINITIONS;
    }

    /**
     * Kinds that create records owned by another module also require that module's
     * create permission (no escalation through `season_copy.execute`).
     */
    public static function requiredPermission(string $kind): ?string
    {
        self::assertApproved($kind);

        return match ($kind) {
            self::CONTRACT_DEFINITIONS => 'contracts.create',
            self::SEASON_PROJECTS => 'season_projects.create',
            self::SEASON_PROJECT_PHASES => 'season_projects.manage_phases',
            self::TASK_DEFINITIONS => 'tasks.create',
            self::SEASON_REMINDER_RULES => 'season_reminders.manage',
            default => null,
        };
    }

    public static function keyFieldFor(string $kind): string
    {
        self::assertApproved($kind);

        return self::MATRIX[$kind]['key'];
    }

    public static function assertApproved(string $kind): void
    {
        if (! self::isApproved($kind)) {
            throw SeasonalTemplatesDomainException::kindDenied($kind);
        }
    }

    /**
     * Sort approved kinds into dependency order and drop duplicates.
     *
     * @param  list<string>  $kinds
     * @return list<string>
     */
    public static function ordered(array $kinds): array
    {
        foreach ($kinds as $kind) {
            self::assertApproved($kind);
        }

        return array_values(array_filter(
            self::kinds(),
            static fn (string $kind): bool => in_array($kind, $kinds, true),
        ));
    }
}
