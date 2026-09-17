<?php

namespace App\Modules\Platform\Controllers;

use App\Core\Shared\ApiResponse;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Platform\Actions\ActivateTenant;
use App\Modules\Platform\Actions\ArchiveTenant;
use App\Modules\Platform\Actions\ListPlatformTenants;
use App\Modules\Platform\Actions\ListPlatformTenantUsers;
use App\Modules\Platform\Actions\ProvisionTenant;
use App\Modules\Platform\Actions\ShowPlatformTenant;
use App\Modules\Platform\Actions\SuspendTenant;
use App\Modules\Platform\Actions\TransferTenantOwnership;
use App\Modules\Platform\Actions\UpdateTenant;
use App\Modules\Platform\Requests\ProvisionTenantRequest;
use App\Modules\Platform\Requests\TenantLifecycleReasonRequest;
use App\Modules\Platform\Requests\TransferTenantOwnershipRequest;
use App\Modules\Platform\Requests\UpdatePlatformTenantRequest;
use App\Modules\Platform\Resources\PlatformTenantResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformTenantController
{
    use AuthorizesRequests;

    public function index(Request $request, ListPlatformTenants $action): JsonResponse
    {
        $this->authorize('viewAny', Tenant::class);

        $paginator = $action->execute([
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'per_page' => (int) $request->query('per_page', 15),
        ]);

        return ApiResponse::success(
            data: PlatformTenantResource::collection($paginator->items())->resolve(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(ProvisionTenantRequest $request, ProvisionTenant $action): JsonResponse
    {
        $this->authorize('create', Tenant::class);

        $result = $action->execute($request->user(), $request->validatedPayload(), $request);
        $tenant = app(ShowPlatformTenant::class)->execute($result['tenant']);

        $data = (new PlatformTenantResource($tenant))->resolve();
        $data['invite_sent'] = $result['invite_sent'];
        $data['invite_code'] = $result['invite_code'];
        $data['password_provisioned'] = $result['password_provisioned'];

        $message = 'تم إنشاء المنشأة ومالكها بنجاح.';
        if ($result['invite_sent'] === true) {
            $message = 'تم إنشاء المنشأة وإرسال دعوة تعيين كلمة المرور للمالك.';
        } elseif (($request->validatedPayload()['owner']['send_invite'] ?? true) === true) {
            $message = 'تم إنشاء المنشأة، لكن تعذر إرسال دعوة تعيين كلمة المرور للمالك.';
        }

        return ApiResponse::success(
            data: $data,
            message: $message,
            status: 201,
        );
    }

    public function show(Tenant $tenant, ShowPlatformTenant $action): JsonResponse
    {
        $this->authorize('view', $tenant);

        return ApiResponse::success(
            data: new PlatformTenantResource($action->execute($tenant)),
        );
    }

    public function update(
        UpdatePlatformTenantRequest $request,
        Tenant $tenant,
        UpdateTenant $action,
        ShowPlatformTenant $show,
    ): JsonResponse {
        $this->authorize('update', $tenant);

        $updated = $action->execute($request->user(), $tenant, $request->validatedPayload(), $request);

        return ApiResponse::success(
            data: new PlatformTenantResource($show->execute($updated)),
            message: 'تم تحديث بيانات المنشأة.',
        );
    }

    public function activate(
        Request $request,
        Tenant $tenant,
        ActivateTenant $action,
        ShowPlatformTenant $show,
    ): JsonResponse {
        $this->authorize('activate', $tenant);

        $updated = $action->execute($request->user(), $tenant, $request);

        return ApiResponse::success(
            data: new PlatformTenantResource($show->execute($updated)),
            message: 'تم تفعيل المنشأة.',
        );
    }

    public function suspend(
        TenantLifecycleReasonRequest $request,
        Tenant $tenant,
        SuspendTenant $action,
        ShowPlatformTenant $show,
    ): JsonResponse {
        $this->authorize('suspend', $tenant);

        $updated = $action->execute($request->user(), $tenant, $request->reason(), $request);

        return ApiResponse::success(
            data: new PlatformTenantResource($show->execute($updated)),
            message: 'تم تعليق المنشأة.',
        );
    }

    public function archive(
        TenantLifecycleReasonRequest $request,
        Tenant $tenant,
        ArchiveTenant $action,
        ShowPlatformTenant $show,
    ): JsonResponse {
        $this->authorize('archive', $tenant);

        $updated = $action->execute($request->user(), $tenant, $request->reason(), $request);

        return ApiResponse::success(
            data: new PlatformTenantResource($show->execute($updated)),
            message: 'تم أرشفة المنشأة.',
        );
    }

    public function transferOwnership(
        TransferTenantOwnershipRequest $request,
        Tenant $tenant,
        TransferTenantOwnership $action,
        ShowPlatformTenant $show,
    ): JsonResponse {
        $this->authorize('transferOwnership', $tenant);

        $updated = $action->execute($request->user(), $tenant, $request->newOwnerId(), $request);

        return ApiResponse::success(
            data: new PlatformTenantResource($show->execute($updated)),
            message: 'تم نقل ملكية المنشأة.',
        );
    }

    public function users(Tenant $tenant, ListPlatformTenantUsers $action): JsonResponse
    {
        $this->authorize('update', $tenant);

        return ApiResponse::success(data: $action->execute($tenant));
    }
}
