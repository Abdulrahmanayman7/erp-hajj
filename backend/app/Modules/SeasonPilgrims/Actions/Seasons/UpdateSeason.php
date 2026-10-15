<?php

namespace App\Modules\SeasonPilgrims\Actions\Seasons;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use App\Modules\SeasonPilgrims\Support\SeasonUniqueness;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateSeason
{
    private const FIELDS = ['code', 'name', 'hajj_year', 'operational_starts_on', 'operational_ends_on', 'notes'];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SeasonUniqueness $uniqueness,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Season $season, array $data, Request $request): Season
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $season, $data, $request, $tenant): Season {
            /** @var Season $locked */
            $locked = Season::query()->whereKey($season->id)->lockForUpdate()->firstOrFail();
            SeasonGuard::assertWritable($locked);

            $changes = array_intersect_key($data, array_flip(self::FIELDS));
            if (isset($changes['code'])) {
                $changes['code'] = trim((string) $changes['code']);
            }
            if (isset($changes['name'])) {
                $changes['name'] = trim((string) $changes['name']);
            }

            $starts = $changes['operational_starts_on'] ?? $locked->operational_starts_on->toDateString();
            $ends = $changes['operational_ends_on'] ?? $locked->operational_ends_on->toDateString();
            if ($ends < $starts) {
                throw SeasonPilgrimDomainException::seasonWindowInvalid();
            }

            $this->uniqueness->assertAvailable(
                (string) ($changes['code'] ?? $locked->code),
                (int) ($changes['hajj_year'] ?? $locked->hajj_year),
                $locked->id,
            );

            $before = $this->snapshot($locked);
            $this->uniqueness->guard(fn () => $locked->update($changes));
            $after = $this->snapshot($locked);

            $this->security->record(AuthorizationSecurityEvent::SEASON_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_id' => $locked->id,
                'season_code' => $locked->code,
                'season_name' => $locked->name,
                'before_values' => array_diff_assoc($before, $after),
                'after_values' => array_diff_assoc($after, $before),
            ], $request);

            return $locked;
        });
    }

    /**
     * @return array<string, string|int|null>
     */
    private function snapshot(Season $season): array
    {
        return [
            'code' => $season->code,
            'name' => $season->name,
            'hajj_year' => $season->hajj_year,
            'operational_starts_on' => $season->operational_starts_on->toDateString(),
            'operational_ends_on' => $season->operational_ends_on->toDateString(),
            'notes' => $season->notes,
        ];
    }
}
