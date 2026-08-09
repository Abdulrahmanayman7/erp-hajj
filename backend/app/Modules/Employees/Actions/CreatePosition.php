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

final class CreatePosition
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array{name: string, code?: string|null, is_active?: bool|null}  $data
     */
    public function execute(User $actor, array $data, Request $request): Position
    {
        $tenant = $this->tenantContext->require();
        $code = $this->normalizeCode($data['code'] ?? null);

        return DB::transaction(function () use ($actor, $data, $request, $tenant, $code): Position {
            if (Position::query()->where('name', $data['name'])->exists()) {
                throw EmployeeDomainException::positionNameTaken();
            }

            if ($code !== null && Position::query()->where('code', $code)->exists()) {
                throw EmployeeDomainException::positionCodeTaken();
            }

            $position = Position::query()->create([
                'name' => $data['name'],
                'code' => $code,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            ]);

            $this->security->record(AuthorizationSecurityEvent::POSITION_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'position_id' => $position->id,
                'name' => $position->name,
                'code' => $position->code,
            ], $request);

            return $position;
        });
    }

    private function normalizeCode(mixed $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $trimmed = trim((string) $code);

        return $trimmed === '' ? null : $trimmed;
    }
}
