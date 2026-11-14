<?php

namespace App\Modules\ExecutiveReports\Controllers;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Shared\ApiResponse;
use App\Core\Tenancy\TenantContext;
use App\Modules\ExecutiveReports\Enums\ExecutiveReportId;
use App\Modules\ExecutiveReports\Requests\GetExecutiveReportRequest;
use App\Modules\ExecutiveReports\Support\ExecutiveReportsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExecutiveReportController
{
    public function show(
        GetExecutiveReportRequest $request,
        ExecutiveReportsService $reports,
        AuthorizationSecurity $security,
        TenantContext $tenantContext,
        string $report,
    ): JsonResponse {
        $reportId = $this->resolveReport($report);
        $seasonId = $request->seasonId();
        $payload = $reports->build($request->user(), $reportId, $seasonId);

        $security->record(AuthorizationSecurityEvent::EXECUTIVE_REPORT_VIEWED, [
            'tenant_id' => $tenantContext->require()->id,
            'actor_id' => $request->user()->id,
            'report_id' => $reportId->value,
            'season_id' => $seasonId,
        ], $request);

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
