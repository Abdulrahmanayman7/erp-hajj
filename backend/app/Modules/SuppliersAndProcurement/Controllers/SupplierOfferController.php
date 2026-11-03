<?php

namespace App\Modules\SuppliersAndProcurement\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SuppliersAndProcurement\Actions\CreateSupplierOffer;
use App\Modules\SuppliersAndProcurement\Actions\ListSupplierOffersForRfq;
use App\Modules\SuppliersAndProcurement\Actions\SubmitSupplierOffer;
use App\Modules\SuppliersAndProcurement\Actions\UpdateSupplierOffer;
use App\Modules\SuppliersAndProcurement\Actions\WithdrawSupplierOffer;
use App\Modules\SuppliersAndProcurement\Models\ProcurementRfq;
use App\Modules\SuppliersAndProcurement\Models\SupplierOffer;
use App\Modules\SuppliersAndProcurement\Requests\CreateSupplierOfferRequest;
use App\Modules\SuppliersAndProcurement\Requests\UpdateSupplierOfferRequest;
use App\Modules\SuppliersAndProcurement\Resources\SupplierOfferResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class SupplierOfferController
{
    use AuthorizesRequests;

    public function index(ProcurementRfq $rfq, ListSupplierOffersForRfq $action): JsonResponse
    {
        $this->authorize('view', $rfq);

        $offers = $action->execute(request()->user(), $rfq);

        return ApiResponse::success(
            data: SupplierOfferResource::collection($offers)->resolve(),
        );
    }

    public function store(
        CreateSupplierOfferRequest $request,
        ProcurementRfq $rfq,
        CreateSupplierOffer $action,
    ): JsonResponse {
        $this->authorize('recordOffer', $rfq);

        $offer = $action->execute($request->user(), $rfq, $request->validated(), $request);

        return ApiResponse::success(
            data: (new SupplierOfferResource($offer))->resolve(),
            status: 201,
        );
    }

    public function update(
        UpdateSupplierOfferRequest $request,
        SupplierOffer $offer,
        UpdateSupplierOffer $action,
    ): JsonResponse {
        $this->authorize('update', $offer);

        $offer = $action->execute($request->user(), $offer, $request->validated(), $request);

        return ApiResponse::success(
            data: (new SupplierOfferResource($offer))->resolve(),
        );
    }

    public function submit(SupplierOffer $offer, SubmitSupplierOffer $action): JsonResponse
    {
        $this->authorize('submit', $offer);

        $offer = $action->execute(request()->user(), $offer, request());

        return ApiResponse::success(
            data: (new SupplierOfferResource($offer))->resolve(),
        );
    }

    public function withdraw(SupplierOffer $offer, WithdrawSupplierOffer $action): JsonResponse
    {
        $this->authorize('withdraw', $offer);

        $offer = $action->execute(request()->user(), $offer, request());

        return ApiResponse::success(
            data: (new SupplierOfferResource($offer))->resolve(),
        );
    }
}
