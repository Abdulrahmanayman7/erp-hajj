<?php

use App\Modules\OperationalKpis\Support\KpiPrecision;

it('formats COUNT values as plain integers', function (): void {
    expect(KpiPrecision::displayCount(0))->toBe('0')
        ->and(KpiPrecision::displayCount(1234))->toBe('1234');
});

it('rounds half up and trims trailing zeros for P07 quantities (max 4 d.p.)', function (string $input, string $expected): void {
    expect(KpiPrecision::displayQuantity($input))->toBe($expected);
})->with([
    'integer' => ['12', '12'],
    'zero' => ['0', '0'],
    'zero with scale' => ['0.0000', '0'],
    'trailing zeros trimmed' => ['10.5000', '10.5'],
    'full 4dp kept' => ['60.1250', '60.125'],
    'half-up at 5th digit' => ['1.23455', '1.2346'],
    'half-up tiny' => ['0.00005', '0.0001'],
    'below half stays' => ['0.00004', '0'],
    'carry into integer' => ['9.99995', '10'],
    'long fraction' => ['3.14159265', '3.1416'],
    'large value' => ['1234567.89125', '1234567.8913'],
]);

it('rounds half up to one decimal for J07/J09 percentages', function (string $input, string $expected): void {
    expect(KpiPrecision::displayPercent($input))->toBe($expected);
})->with([
    'repeating third' => ['33.33333333', '33.3'],
    'repeating two thirds' => ['66.66666667', '66.7'],
    'exact half goes up' => ['33.35', '33.4'],
    'binary-float trap 1.005-style' => ['2.25', '2.3'],
    'zero keeps one decimal' => ['0', '0.0'],
    'hundred keeps one decimal' => ['100', '100.0'],
    'below half' => ['0.04', '0.0'],
    'carry' => ['99.95', '100.0'],
]);

it('rounds half away from zero for negative inputs', function (): void {
    expect(KpiPrecision::roundHalfUp('-1.25', 1))->toBe('-1.3')
        ->and(KpiPrecision::roundHalfUp('-0.04', 1))->toBe('0.0');
});

it('normalises DB aggregates (string, int, float, null) into bcmath-safe decimals', function (): void {
    expect(KpiPrecision::toDecimalString(null))->toBe('0')
        ->and(KpiPrecision::toDecimalString(''))->toBe('0')
        ->and(KpiPrecision::toDecimalString(7))->toBe('7')
        ->and(KpiPrecision::toDecimalString('12.5000'))->toBe('12.5000')
        ->and(KpiPrecision::toDecimalString(33.333333333333336))->toBe('33.33333333')
        ->and(KpiPrecision::toDecimalString(1.0E-5))->toBe('0.00001000');
});

it('serves server-rounded display values over the API without re-rounding the numeric value', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);

    // P07 = 10.5000 → trailing zeros trimmed → "10.5"
    kpiPurchaseOrder($tenant, ['season_id' => $season->id, 'status' => 'issued', 'qty' => '20.0000', 'received' => '10.5000']);
    // J07 = (0 + 100 + 100) / 3 = 66.666… → 66.7 ; J09 = 1 completed / (1 active + 1 completed) → 50.0
    kpiProject($tenant, $season, 'draft', ['progress_percent' => 0]);
    kpiProject($tenant, $season, 'active', ['progress_percent' => 100]);
    kpiProject($tenant, $season, 'completed', ['progress_percent' => 100]);

    kpiActingAsUserWith(['operational_kpis.view', 'purchase_orders.view', 'season_projects.view'], $tenant);

    $map = kpiMap(kpiGet($season->id)->assertOk());

    expect($map['P07']['display_value'])->toBe('10.5')
        ->and($map['P07']['value'])->toEqualWithDelta(10.5, 0.00001)
        ->and($map['J07']['display_value'])->toBe('66.7')
        ->and($map['J07']['value'])->toEqualWithDelta(66.6666666667, 0.0001)
        ->and($map['J09']['display_value'])->toBe('50.0')
        ->and($map['J09']['value'])->toEqualWithDelta(50.0, 0.0001)
        ->and($map['J01']['display_value'])->toBe('3');
});

it('shows display_value as an integer string for every COUNT KPI', function (): void {
    $tenant = actingAsTenantOwner()->tenant;
    $season = kpiSeason($tenant);
    kpiProject($tenant, $season, 'active', ['progress_percent' => 10]);
    kpiActingAsUserWith(['operational_kpis.view', 'season_projects.view'], $tenant);

    $map = kpiMap(kpiGet($season->id)->assertOk());

    foreach (['J01', 'J02', 'J03'] as $id) {
        expect($map[$id]['display_value'])->toMatch('/^\d+$/');
    }
});
