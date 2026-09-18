<?php

namespace App\Modules\Employees\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Employees\Exceptions\EmployeeDomainException;
use App\Modules\Employees\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdatePosition
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{name?: string, code?: string|null, is_active?: bool|null}  $data
     */
    public function execute(User $actor, Position $position, array $data, Request $request): Position
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $position, $data, $request, $tenant): Position {
            $locked = Position::query()->whereKey($position->id)->lockForUpdate()->firstOrFail();

            if (array_key_exists('code', $data)) {
                $incoming = $data['code'];
                $normalized = $incoming === null || trim((string) $incoming) === ''
                    ? null
                    : trim((string) $incoming);

                if ($normalized !== $locked->code) {
                    throw EmployeeDomainException::positionCodeImmutable();
                }
            }

            if (array_key_exists('name', $data)) {
                if (Position::query()->where('name', $data['name'])->whereKeyNot($locked->id)->exists()) {
                    throw EmployeeDomainException::positionNameTaken();
                }
                $locked->name = $data['name'];
            }

            if (array_key_exists('is_active', $data) && $data['is_active'] !== null) {
                $locked->is_active = (bool) $data['is_active'];
            }

            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::POSITION_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'position_id' => $locked->id,
                'name' => $locked->name,
            ], $request);

            return $locked;
        });
    }
}
