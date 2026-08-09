<?php

namespace App\Modules\Contracts\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\Contracts\Actions\ApproveContract;
use App\Modules\Contracts\Actions\CancelContract;
use App\Modules\Contracts\Actions\CloseContract;
use App\Modules\Contracts\Actions\CreateContract;
use App\Modules\Contracts\Actions\DeleteContract;
use App\Modules\Contracts\Actions\ExecuteContract;
use App\Modules\Contracts\Actions\ListContracts;
use App\Modules\Contracts\Actions\RenewContract;
use App\Modules\Contracts\Actions\ReturnContractToDraft;
use App\Modules\Contracts\Actions\SignContract;
use App\Modules\Contracts\Actions\SubmitContractForReview;
use App\Modules\Contracts\Actions\UpdateContract;
use App\Modules\Contracts\Models\Contract;
use App\Modules\Contracts\Requests\ContractTransitionRequest;
use App\Modules\Contracts\Requests\CreateContractRequest;
use App\Modules\Contracts\Requests\UpdateContractRequest;
use App\Modules\Contracts\Resources\ContractResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractController
{
    use AuthorizesRequests;

    public function index(Request $request, ListContracts $action): JsonResponse
    {
        $this->authorize('viewAny', Contract::class);

        $paginator = $action->execute([
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'category_id' => $request->query('category_id'),
            'employee_id' => $request->query('employee_id'),
            'organization_unit_id' => $request->query('organization_unit_id'),
            'expiring_soon' => $request->query('expiring_soon'),
            'start_date_from' => $request->query('start_date_from'),
            'start_date_to' => $request->query('start_date_to'),
            'end_date_from' => $request->query('end_date_from'),
            'end_date_to' => $request->query('end_date_to'),
            'sort' => $request->query('sort'),
            'direction' => $request->query('direction'),
            'per_page' => $request->query('per_page'),
            'page' => $request->query('page'),
        ]);

        return ApiResponse::success(
            data: ContractResource::collection($paginator->items())->resolve(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(CreateContractRequest $request, CreateContract $action): JsonResponse
    {
        $this->authorize('create', Contract::class);

        $contract = $action->execute($request->user(), $request->validated(), $request);

        return ApiResponse::success(
            data: (new ContractResource($contract))->resolve(),
            message: 'تم إنشاء العقد بنجاح',
            status: 201,
        );
    }

    public function show(Contract $contract): JsonResponse
    {
        $this->authorize('view', $contract);

        $contract->load([
            'category',
            'employee',
            'organizationUnit',
            'createdBy',
            'renewedFrom',
            'renewalChild',
            'statusTransitions.actor',
        ]);

        return ApiResponse::success(data: (new ContractResource($contract))->resolve());
    }

    public function update(
        UpdateContractRequest $request,
        Contract $contract,
        UpdateContract $action,
    ): JsonResponse {
        $this->authorize('update', $contract);

        $contract = $action->execute($request->user(), $contract, $request->validated(), $request);

        return ApiResponse::success(
            data: (new ContractResource($contract))->resolve(),
            message: 'تم تحديث العقد بنجاح',
        );
    }

    public function destroy(Request $request, Contract $contract, DeleteContract $action): JsonResponse
    {
        $this->authorize('delete', $contract);

        $action->execute($request->user(), $contract, $request);

        return ApiResponse::success(
            data: null,
            message: 'تم حذف العقد',
        );
    }

    public function submitReview(
        ContractTransitionRequest $request,
        Contract $contract,
        SubmitContractForReview $action,
    ): JsonResponse {
        $this->authorize('review', $contract);

        $contract = $action->execute(
            $request->user(),
            $contract,
            $request->validated('comment'),
            $request,
        );

        return ApiResponse::success(
            data: (new ContractResource($contract))->resolve(),
            message: 'تم إرسال العقد للمراجعة',
        );
    }

    public function returnDraft(
        ContractTransitionRequest $request,
        Contract $contract,
        ReturnContractToDraft $action,
    ): JsonResponse {
        $this->authorize('review', $contract);

        $contract = $action->execute(
            $request->user(),
            $contract,
            $request->validated('comment'),
            $request,
        );

        return ApiResponse::success(
            data: (new ContractResource($contract))->resolve(),
            message: 'تم إرجاع العقد إلى المسودة',
        );
    }

    public function approve(
        ContractTransitionRequest $request,
        Contract $contract,
        ApproveContract $action,
    ): JsonResponse {
        $this->authorize('approve', $contract);

        $contract = $action->execute(
            $request->user(),
            $contract,
            $request->validated('comment'),
            $request,
        );

        return ApiResponse::success(
            data: (new ContractResource($contract))->resolve(),
            message: 'تم اعتماد العقد',
        );
    }

    public function sign(
        ContractTransitionRequest $request,
        Contract $contract,
        SignContract $action,
    ): JsonResponse {
        $this->authorize('sign', $contract);

        $contract = $action->execute(
            $request->user(),
            $contract,
            $request->validated('comment'),
            $request,
        );

        return ApiResponse::success(
            data: (new ContractResource($contract))->resolve(),
            message: 'تم تسجيل توقيع العقد',
        );
    }

    public function execute(
        ContractTransitionRequest $request,
        Contract $contract,
        ExecuteContract $action,
    ): JsonResponse {
        $this->authorize('execute', $contract);

        $contract = $action->execute(
            $request->user(),
            $contract,
            $request->validated('comment'),
            $request,
        );

        return ApiResponse::success(
            data: (new ContractResource($contract))->resolve(),
            message: 'تم بدء تنفيذ العقد',
        );
    }

    public function close(
        ContractTransitionRequest $request,
        Contract $contract,
        CloseContract $action,
    ): JsonResponse {
        $this->authorize('close', $contract);

        $contract = $action->execute(
            $request->user(),
            $contract,
            $request->validated('comment'),
            $request,
        );

        return ApiResponse::success(
            data: (new ContractResource($contract))->resolve(),
            message: 'تم إغلاق العقد',
        );
    }

    public function cancel(
        ContractTransitionRequest $request,
        Contract $contract,
        CancelContract $action,
    ): JsonResponse {
        $this->authorize('cancel', $contract);

        $contract = $action->execute(
            $request->user(),
            $contract,
            $request->validated('comment'),
            $request,
        );

        return ApiResponse::success(
            data: (new ContractResource($contract))->resolve(),
            message: 'تم إلغاء العقد',
        );
    }

    public function renew(
        ContractTransitionRequest $request,
        Contract $contract,
        RenewContract $action,
    ): JsonResponse {
        $this->authorize('renew', $contract);

        $result = $action->execute(
            $request->user(),
            $contract,
            $request->validated('comment'),
            $request,
        );

        return ApiResponse::success(
            data: [
                'source' => (new ContractResource($result['source']))->resolve(),
                'successor' => (new ContractResource($result['successor']))->resolve(),
            ],
            message: 'تم تجديد العقد',
        );
    }
}
