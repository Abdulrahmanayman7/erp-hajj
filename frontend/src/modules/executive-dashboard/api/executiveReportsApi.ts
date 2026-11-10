import { apiDownloadBlob } from '@/shared/api/http'

export const EXECUTIVE_REPORT_IDS = [
  'season-kpi-summary',
  'operational-alerts',
  'transport-movements',
  'accommodation-occupancy',
  'overdue-purchase-approvals',
  'risks-and-complaints',
] as const

export type ExecutiveReportId = (typeof EXECUTIVE_REPORT_IDS)[number]

export function executiveReportCsvPath(report: ExecutiveReportId, seasonId: number): string {
  return `/api/v1/executive-reports/${report}/export.csv?season_id=${encodeURIComponent(String(seasonId))}`
}

export async function downloadExecutiveReportCsv(
  report: ExecutiveReportId,
  seasonId: number,
): Promise<{ blob: Blob; filename: string | null }> {
  return apiDownloadBlob(executiveReportCsvPath(report, seasonId))
}
