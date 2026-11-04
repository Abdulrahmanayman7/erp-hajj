import { apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type {
  CreateOfferPayload,
  CreateRfqPayload,
  ProcurementRfq,
  RfqInvitation,
  SelectSupplierPayload,
  SupplierOffer,
} from '../types/rfq'

const base = '/api/v1/rfqs'

function toQuery(filters: Record<string, string | number | undefined> = {}): string {
  const params = new URLSearchParams()
  Object.entries(filters).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    params.set(key, String(value))
  })
  const qs = params.toString()
  return qs ? `?${qs}` : ''
}

export async function listRfqs(params: {
  status?: string
  purchase_request_id?: number
  q?: string
  page?: number
} = {}): Promise<{ data: ProcurementRfq[]; meta: Record<string, number> }> {
  const response = await apiGet<ProcurementRfq[]>(`${base}${toQuery(params)}`)
  return { data: response.data, meta: (response.meta as Record<string, number>) ?? {} }
}

export async function getRfq(id: number): Promise<ProcurementRfq> {
  const response = await apiGet<ProcurementRfq>(`${base}/${id}`)
  return response.data
}

export async function createRfq(payload: CreateRfqPayload): Promise<ProcurementRfq> {
  const response = await apiPost<ProcurementRfq>(base, payload)
  return response.data
}

export async function publishRfq(id: number): Promise<ProcurementRfq> {
  const response = await apiPost<ProcurementRfq>(`${base}/${id}/publish`)
  return response.data
}

export async function cancelRfq(id: number, reason: string): Promise<ProcurementRfq> {
  const response = await apiPost<ProcurementRfq>(`${base}/${id}/cancel`, { reason })
  return response.data
}

export async function listRfqInvitations(rfqId: number): Promise<RfqInvitation[]> {
  const response = await apiGet<RfqInvitation[]>(`${base}/${rfqId}/invitations`)
  return response.data
}

export async function inviteSupplierToRfq(rfqId: number, supplierId: number): Promise<RfqInvitation> {
  const response = await apiPost<RfqInvitation>(`${base}/${rfqId}/invitations`, {
    supplier_id: supplierId,
  })
  return response.data
}

export async function listRfqOffers(rfqId: number): Promise<SupplierOffer[]> {
  const response = await apiGet<SupplierOffer[]>(`${base}/${rfqId}/offers`)
  return response.data
}

export async function createRfqOffer(rfqId: number, payload: CreateOfferPayload): Promise<SupplierOffer> {
  const response = await apiPost<SupplierOffer>(`${base}/${rfqId}/offers`, payload)
  return response.data
}

export async function submitOffer(offerId: number): Promise<SupplierOffer> {
  const response = await apiPost<SupplierOffer>(`/api/v1/offers/${offerId}/submit`)
  return response.data
}

export async function withdrawOffer(offerId: number): Promise<SupplierOffer> {
  const response = await apiPost<SupplierOffer>(`/api/v1/offers/${offerId}/withdraw`)
  return response.data
}

export async function updateOffer(
  offerId: number,
  payload: Partial<CreateOfferPayload>,
): Promise<SupplierOffer> {
  const response = await apiPatch<SupplierOffer>(`/api/v1/offers/${offerId}`, payload)
  return response.data
}

export async function selectRfqSupplier(
  rfqId: number,
  payload: SelectSupplierPayload,
): Promise<ProcurementRfq> {
  const response = await apiPost<ProcurementRfq>(`${base}/${rfqId}/select-supplier`, payload)
  return response.data
}
