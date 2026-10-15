<?php

namespace App\Modules\SeasonPilgrims\Actions\Programs;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Models\SeasonProgram;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * F25: a deactivated program blocks new assignments while existing
 * assignments and history remain untouched.
 */
final class SetSeasonProgramActive
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, SeasonProgram $program, bool $active, Request $request): SeasonProgram
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $program, $active, $request, $tenant): SeasonProgram {
            /** @var SeasonProgram $locked */
            $locked = SeasonProgram::query()->whereKey($program->id)->lockForUpdate()->firstOrFail();
            SeasonGuard::assertWritable($locked->season()->firstOrFail());

            if ($locked->is_active === $active) {
                return $locked;
            }

            $locked->update(['is_active' => $active]);

            $this->security->record(
                $active ? AuthorizationSecurityEvent::SEASON_PROGRAM_ACTIVATED : AuthorizationSecurityEvent::SEASON_PROGRAM_DEACTIVATED,
                [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'season_program_id' => $locked->id,
                    'season_program_code' => $locked->code,
                    'season_program_name' => $locked->name,
                    'before_values' => ['is_active' => ! $active],
                    'after_values' => ['is_active' => $active],
                    'metadata' => ['season_id' => $locked->season_id],
                ],
                $request,
            );

            return $locked;
        });
    }
}
