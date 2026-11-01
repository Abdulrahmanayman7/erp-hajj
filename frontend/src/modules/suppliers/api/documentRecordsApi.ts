import { apiGet, apiPost } from '@/shared/api/http'

import type {
  CreateDocumentRecordPayload,
  SupersedeDocumentRecordPayload,
  SupplierDocumentRecord,
} from '../types/eligibility'

function base(supplierId: number): string {
  return `/api/v1/suppliers/${supplierId}/document-records`
}

export async function listSupplierDocumentRecords(
  supplierId: number,
): Promise<SupplierDocumentRecord[]> {
  const response = await apiGet<SupplierDocumentRecord[]>(base(supplierId))
  return response.data
}

export async function createSupplierDocumentRecord(
  supplierId: number,
  payload: CreateDocumentRecordPayload,
): Promise<SupplierDocumentRecord> {
  const response = await apiPost<SupplierDocumentRecord>(base(supplierId), payload)
  return response.data
}

export async function verifySupplierDocumentRecord(
  supplierId: number,
  recordId: number,
  comment?: string | null,
): Promise<SupplierDocumentRecord> {
  const body = comment && comment.trim() ? { comment: comment.trim() } : {}
  const response = await apiPost<SupplierDocumentRecord>(
    `${base(supplierId)}/${recordId}/verify`,
    body,
  )
  return response.data
}

export async function rejectSupplierDocumentRecord(
  supplierId: number,
  recordId: number,
  rejectionReason: string,
): Promise<SupplierDocumentRecord> {
  const response = await apiPost<SupplierDocumentRecord>(
    `${base(supplierId)}/${recordId}/reject`,
    { rejection_reason: rejectionReason },
  )
  return response.data
}

export async function supersedeSupplierDocumentRecord(
  supplierId: number,
  recordId: number,
  payload: SupersedeDocumentRecordPayload,
): Promise<SupplierDocumentRecord> {
  const response = await apiPost<SupplierDocumentRecord>(
    `${base(supplierId)}/${recordId}/supersede`,
    payload,
  )
  return response.data
}
