<?php

namespace App\Modules\ExecutiveReports\Controllers;

use App\Core\Shared\ApiResponse;
use App\Modules\ExecutiveReports\Enums\ExecutiveReportId;
use App\Modules\ExecutiveReports\Requests\GetExecutiveReportRequest;
use App\Modules\ExecutiveReports\Support\ExecutiveReportsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExecutiveReportController
{
    public function show(GetExecutiveReportRequest $request, ExecutiveReportsService $reports, string $report): JsonResponse
    {
        $reportId = $this->resolveReport($report);
        $payload = $reports->build($request->user(), $reportId, $request->seasonId());

        return ApiResponse::success(data: $payload);
    }

    public function exportCsv(GetExecutiveReportRequest $request, ExecutiveReportsService $reports, string $report): Response
    {
        $reportId = $this->resolveReport($report);
        $csv = $reports->exportCsv($request->user(), $reportId, $request->seasonId(), $request);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$reportId->value.'.csv"',
        ]);
    }

    private function resolveReport(string $report): ExecutiveReportId
    {
        $resolved = ExecutiveReportId::tryFrom($report);
        if ($resolved === null) {
            throw new NotFoundHttpException;
        }

        return $resolved;
    }
}
