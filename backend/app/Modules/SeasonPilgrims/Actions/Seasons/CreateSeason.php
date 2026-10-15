<?php

namespace App\Modules\SeasonPilgrims\Actions\Seasons;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Enums\SeasonStatus;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Support\SeasonUniqueness;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateSeason
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SeasonUniqueness $uniqueness,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data, Request $request): Season
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $data, $request, $tenant): Season {
            $code = trim((string) $data['code']);
            $hajjYear = (int) $data['hajj_year'];
            $this->uniqueness->assertAvailable($code, $hajjYear);

            $season = $this->uniqueness->guard(fn (): Season => Season::query()->create([
                'code' => $code,
                'name' => trim((string) $data['name']),
                'hajj_year' => $hajjYear,
                'operational_starts_on' => $data['operational_starts_on'],
                'operational_ends_on' => $data['operational_ends_on'],
                'notes' => $data['notes'] ?? null,
                'status' => SeasonStatus::Draft,
                'created_by' => $actor->id,
            ]));

            $this->security->record(AuthorizationSecurityEvent::SEASON_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_id' => $season->id,
                'season_code' => $season->code,
                'season_name' => $season->name,
                'to_status' => SeasonStatus::Draft->value,
                'metadata' => ['hajj_year' => $season->hajj_year],
            ], $request);

            return $season;
        });
    }
}
