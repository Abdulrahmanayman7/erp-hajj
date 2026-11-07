<?php

namespace App\Modules\SeasonalTemplates\Support;

use App\Modules\SeasonalTemplates\Adapters\AccommodationSeasonFacilitiesSafeCopyAdapter;
use App\Modules\SeasonalTemplates\Adapters\ContractDefinitionsSafeCopyAdapter;
use App\Modules\SeasonalTemplates\Adapters\SeasonPilgrimGroupsSafeCopyAdapter;
use App\Modules\SeasonalTemplates\Adapters\SeasonProgramsSafeCopyAdapter;
use App\Modules\SeasonalTemplates\Adapters\SeasonProjectPhasesSafeCopyAdapter;
use App\Modules\SeasonalTemplates\Adapters\SeasonProjectsSafeCopyAdapter;
use App\Modules\SeasonalTemplates\Adapters\TaskDefinitionsSafeCopyAdapter;
use App\Modules\SeasonalTemplates\Adapters\TransportEntitlementsSafeCopyAdapter;
use App\Modules\SeasonalTemplates\Adapters\TransportSeasonVehiclesSafeCopyAdapter;
use App\Modules\SeasonalTemplates\Exceptions\SeasonalTemplatesDomainException;

/**
 * The only way to reach an adapter: a kind must be on the {@see SafeCopyAllowlist}
 * AND have a registered adapter (deny by default).
 */
final class SafeCopyAdapterRegistry
{
    /**
     * @var array<string, SafeCopyAdapter>
     */
    private array $adapters = [];

    public function __construct(
        SeasonProgramsSafeCopyAdapter $programs,
        SeasonPilgrimGroupsSafeCopyAdapter $groups,
        TransportEntitlementsSafeCopyAdapter $entitlements,
        TransportSeasonVehiclesSafeCopyAdapter $vehicles,
        AccommodationSeasonFacilitiesSafeCopyAdapter $facilities,
        ContractDefinitionsSafeCopyAdapter $contracts,
        SeasonProjectsSafeCopyAdapter $projects,
        SeasonProjectPhasesSafeCopyAdapter $phases,
        TaskDefinitionsSafeCopyAdapter $tasks,
    ) {
        foreach ([$programs, $groups, $entitlements, $vehicles, $facilities, $contracts, $projects, $phases, $tasks] as $adapter) {
            $this->adapters[$adapter->entityKind()] = $adapter;
        }
    }

    public function get(string $kind): SafeCopyAdapter
    {
        SafeCopyAllowlist::assertApproved($kind);

        return $this->adapters[$kind] ?? throw SeasonalTemplatesDomainException::kindDenied($kind);
    }
}
