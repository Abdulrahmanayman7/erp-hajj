<?php

namespace App\Modules\Employees\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Employees\Exceptions\EmployeeDomainException;
use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeletePosition
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Position $position, Request $request): void
    {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $position, $request, $tenant): void {
            $locked = Position::query()->whereKey($position->id)->lockForUpdate()->firstOrFail();

            if (Employee::query()->where('position_id', $locked->id)->exists()) {
                throw EmployeeDomainException::positionInUse();
            }

            $snapshot = [
                'id' => $locked->id,
                'name' => $locked->name,
                'code' => $locked->code,
            ];

            $locked->delete();

            $this->security->record(AuthorizationSecurityEvent::POSITION_DELETED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'position' => $snapshot,
            ], $request);
        });
    }
}
