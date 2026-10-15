<?php

namespace App\Modules\SeasonPilgrims\Actions\Programs;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonProgram;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateSeasonProgram
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Season $season, array $data, Request $request): SeasonProgram
    {
        $tenant = $this->tenantContext->require();
        SeasonGuard::assertWritable($season);

        return DB::transaction(function () use ($actor, $season, $data, $request, $tenant): SeasonProgram {
            $code = trim((string) $data['code']);
            if (SeasonProgram::query()->where('season_id', $season->id)->where('code', $code)->exists()) {
                throw SeasonPilgrimDomainException::programCodeTaken();
            }

            try {
                $program = SeasonProgram::query()->create([
                    'season_id' => $season->id,
                    'code' => $code,
                    'name' => trim((string) $data['name']),
                    'description' => $data['description'] ?? null,
                    'is_active' => true,
                    'created_by' => $actor->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw SeasonPilgrimDomainException::programCodeTaken();
            }

            $this->security->record(AuthorizationSecurityEvent::SEASON_PROGRAM_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_program_id' => $program->id,
                'season_program_code' => $program->code,
                'season_program_name' => $program->name,
                'metadata' => ['season_id' => $season->id],
            ], $request);

            return $program;
        });
    }
}
