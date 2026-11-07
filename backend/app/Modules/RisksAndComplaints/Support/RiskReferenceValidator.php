<?php

namespace App\Modules\RisksAndComplaints\Support;

use App\Modules\Accommodation\Models\AccommodationFacility;
use App\Modules\Accommodation\Models\AccommodationSeasonFacility;
use App\Modules\Employees\Enums\EmployeeStatus;
use App\Modules\Employees\Models\Employee;
use App\Modules\RisksAndComplaints\Exceptions\RisksAndComplaintsDomainException;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroup;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class RiskReferenceValidator
{
    public function resolveActiveEmployee(int $employeeId): Employee
    {
        $employee = Employee::query()->whereKey($employeeId)->first();

        if ($employee === null || $employee->status !== EmployeeStatus::Active) {
            throw RisksAndComplaintsDomainException::riskEmployeeInvalid();
        }

        return $employee;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *   season_pilgrim_id: int|null,
     *   group_id: int|null,
     *   facility_id: int|null,
     *   transport_season_vehicle_id: int|null,
     *   project_id: int|null
     * }
     */
    public function resolveOptionalLinks(Season $season, array $data, bool $partial = false): array
    {
        $out = [
            'season_pilgrim_id' => null,
            'group_id' => null,
            'facility_id' => null,
            'transport_season_vehicle_id' => null,
            'project_id' => null,
        ];

        if (array_key_exists('season_pilgrim_id', $data) || ! $partial) {
            $out['season_pilgrim_id'] = $this->resolveSeasonPilgrim(
                $season,
                array_key_exists('season_pilgrim_id', $data) ? $data['season_pilgrim_id'] : null,
            );
        }

        if (array_key_exists('group_id', $data) || ! $partial) {
            $out['group_id'] = $this->resolveGroup(
                $season,
                array_key_exists('group_id', $data) ? $data['group_id'] : null,
            );
        }

        if (array_key_exists('facility_id', $data) || ! $partial) {
            $out['facility_id'] = $this->resolveFacility(
                $season,
                array_key_exists('facility_id', $data) ? $data['facility_id'] : null,
            );
        }

        if (array_key_exists('transport_season_vehicle_id', $data) || ! $partial) {
            $out['transport_season_vehicle_id'] = $this->resolveTransportSeasonVehicle(
                $season,
                array_key_exists('transport_season_vehicle_id', $data) ? $data['transport_season_vehicle_id'] : null,
            );
        }

        if (array_key_exists('project_id', $data) || ! $partial) {
            $out['project_id'] = $this->resolveProject(
                $season,
                array_key_exists('project_id', $data) ? $data['project_id'] : null,
            );
        }

        return $out;
    }

    public function resolveSeasonPilgrim(Season $season, mixed $id): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }

        $row = SeasonPilgrim::query()->whereKey((int) $id)->first();
        if ($row === null || (int) $row->season_id !== (int) $season->id) {
            throw RisksAndComplaintsDomainException::linkedEntityInvalid();
        }

        return (int) $row->id;
    }

    public function resolveGroup(Season $season, mixed $id): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }

        $row = SeasonPilgrimGroup::query()->whereKey((int) $id)->first();
        if ($row === null || (int) $row->season_id !== (int) $season->id) {
            throw RisksAndComplaintsDomainException::linkedEntityInvalid();
        }

        return (int) $row->id;
    }

    public function resolveFacility(Season $season, mixed $id): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }

        $facility = AccommodationFacility::query()->whereKey((int) $id)->first();
        if ($facility === null || ! $facility->is_active) {
            throw RisksAndComplaintsDomainException::linkedEntityInvalid();
        }

        $linked = AccommodationSeasonFacility::query()
            ->where('facility_id', $facility->id)
            ->where('season_id', $season->id)
            ->exists();

        if (! $linked) {
            throw RisksAndComplaintsDomainException::linkedEntityInvalid();
        }

        return (int) $facility->id;
    }

    public function resolveTransportSeasonVehicle(Season $season, mixed $id): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }

        $row = TransportSeasonVehicle::query()->whereKey((int) $id)->first();
        if ($row === null || (int) $row->season_id !== (int) $season->id) {
            throw RisksAndComplaintsDomainException::linkedEntityInvalid();
        }

        return (int) $row->id;
    }

    public function resolveProject(Season $season, mixed $id): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }

        if (! Schema::hasTable('season_projects')) {
            throw RisksAndComplaintsDomainException::seasonProjectLinkUnavailable();
        }

        $row = DB::table('season_projects')
            ->where('id', (int) $id)
            ->where('tenant_id', $season->tenant_id)
            ->where('season_id', $season->id)
            ->first();

        if ($row === null) {
            throw RisksAndComplaintsDomainException::linkedEntityInvalid();
        }

        return (int) $row->id;
    }
}
