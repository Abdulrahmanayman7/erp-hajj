<?php

namespace App\Modules\SeasonPilgrims\Actions\Programs;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\SeasonProgram;
use App\Modules\SeasonPilgrims\Support\SeasonGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * F25: administrative metadata edit (code, name, description), audited.
 */
final class UpdateSeasonProgram
{
    private const FIELDS = ['code', 'name', 'description'];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, SeasonProgram $program, array $data, Request $request): SeasonProgram
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $program, $data, $request, $tenant): SeasonProgram {
            /** @var SeasonProgram $locked */
            $locked = SeasonProgram::query()->whereKey($program->id)->lockForUpdate()->firstOrFail();
            SeasonGuard::assertWritable($locked->season()->firstOrFail());

            $changes = array_intersect_key($data, array_flip(self::FIELDS));
            if (isset($changes['code'])) {
                $changes['code'] = trim((string) $changes['code']);
                $taken = SeasonProgram::query()
                    ->where('season_id', $locked->season_id)
                    ->where('code', $changes['code'])
                    ->whereKeyNot($locked->id)
                    ->exists();
                if ($taken) {
                    throw SeasonPilgrimDomainException::programCodeTaken();
                }
            }

            $before = $locked->only(self::FIELDS);
            $locked->update($changes);
            $after = $locked->only(self::FIELDS);

            $this->security->record(AuthorizationSecurityEvent::SEASON_PROGRAM_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'season_program_id' => $locked->id,
                'season_program_code' => $locked->code,
                'season_program_name' => $locked->name,
                'before_values' => array_diff_assoc($before, $after),
                'after_values' => array_diff_assoc($after, $before),
                'metadata' => ['season_id' => $locked->season_id],
            ], $request);

            return $locked;
        });
    }
}
