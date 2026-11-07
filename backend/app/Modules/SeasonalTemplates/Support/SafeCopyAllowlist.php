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
    public const MATRIX_VERSION = '2026-11-07.1';

    public const SEASON_PROGRAMS = 'season_programs';

    public const SEASON_PILGRIM_GROUPS = 'season_pilgrim_groups';

    public const TRANSPORT_ENTITLEMENTS = 'transport_entitlements';

    public const TRANSPORT_SEASON_VEHICLES = 'transport_season_vehicles';

    public const ACCOMMODATION_SEASON_FACILITIES = 'accommodation_season_facilities';

    /**
     * Approved kinds in dependency order (programs before their entitlements).
     * Each entry: natural-key field + the only fields an adapter may carry.
     *
     * @var array<string, array{key: string, fields: list<string>}>
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
