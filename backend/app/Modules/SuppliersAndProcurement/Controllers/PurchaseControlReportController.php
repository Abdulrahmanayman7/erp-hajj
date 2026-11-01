<?php

namespace App\Modules\SuppliersAndProcurement\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\SuppliersAndProcurement\Models\PurchaseDemandGroup;
use App\Modules\SuppliersAndProcurement\Requests\PurchaseControlReportRequest;
use App\Modules\SuppliersAndProcurement\Support\PurchaseControlReports\PurchaseControlReportsService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseControlReportController
{
    use AuthorizesRequests;

    public function approvalLatency(
        PurchaseControlReportRequest $request,
        PurchaseControlReportsService $reports,
    ): JsonResponse {
        $this->authorize('viewControlReports', PurchaseDemandGroup::class);

        $data = $reports->approvalLatency($request->user(), $request->validated(), $request);

        return ApiResponse::success(data: $data);
    }

    public function overdue(
        PurchaseControlReportRequest $request,
        PurchaseControlReportsService $reports,
    ): JsonResponse {
        $this->authorize('viewControlReports', PurchaseDemandGroup::class);

        $data = $reports->overdue($request->user(), $request->validated(), $request);

        return ApiResponse::success(data: $data);
    }

    public function outcomes(
        PurchaseControlReportRequest $request,
        PurchaseControlReportsService $reports,
    ): JsonResponse {
        $this->authorize('viewControlReports', PurchaseDemandGroup::class);

        $data = $reports->outcomes($request->user(), $request->validated(), $request);

        return ApiResponse::success(data: $data);
    }

    public function compliance(
        PurchaseControlReportRequest $request,
        PurchaseControlReportsService $reports,
    ): JsonResponse {
        $this->authorize('viewControlReports', PurchaseDemandGroup::class);

        $data = $reports->compliance($request->user(), $request->validated(), $request);

        return ApiResponse::success(data: $data);
    }

    public function exportApprovalLatency(
        PurchaseControlReportRequest $request,
        PurchaseControlReportsService $reports,
    ): Response|StreamedResponse {
        return $this->export($request, $reports, 'approval-latency');
    }

    public function exportOverdue(
        PurchaseControlReportRequest $request,
        PurchaseControlReportsService $reports,
    ): Response|StreamedResponse {
        return $this->export($request, $reports, 'overdue');
    }

    public function exportOutcomes(
        PurchaseControlReportRequest $request,
        PurchaseControlReportsService $reports,
    ): Response|StreamedResponse {
        return $this->export($request, $reports, 'outcomes');
    }

    public function exportCompliance(
        PurchaseControlReportRequest $request,
        PurchaseControlReportsService $reports,
    ): Response|StreamedResponse {
        return $this->export($request, $reports, 'compliance');
    }

    private function export(
        PurchaseControlReportRequest $request,
        PurchaseControlReportsService $reports,
        string $reportKey,
    ): Response {
        $this->authorize('exportControlReports', PurchaseDemandGroup::class);

        $csv = $reports->exportCsv($request->user(), $reportKey, $request->validated(), $request);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$reportKey.'.csv"',
        ]);
    }
}
