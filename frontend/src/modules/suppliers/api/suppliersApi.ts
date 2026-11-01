import { apiDelete, apiGet, apiPatch, apiPost, type ApiSuccess } from '@/shared/api/http'

import type { SupplierEligibility } from '../types/eligibility'
import type {
  AddSupplierCoveragePayload,
  CreateSupplierContactPayload,
  CreateSupplierPayload,
  GeoPlace,
  ListSuppliersParams,
  Supplier,
  SupplierContact,
  SupplierCoverage,
  SupplierMutationResult,
  SupplierTransitionPayload,
  SupplierWarning,
  SuppliersListMeta,
  UpdateSupplierContactPayload,
  UpdateSupplierPayload,
} from '../types/suppliers'

type SuccessWithWarnings<T> = ApiSuccess<T> & { warnings?: SupplierWarning[] }

function toQuery(params: ListSuppliersParams): string {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') {
      return
    }
    query.set(key, String(value))
  })
  const qs = query.toString()
  return qs ? `?${qs}` : ''
}

function withWarnings<T>(response: SuccessWithWarnings<T>): SupplierMutationResult {
  return {
    supplier: response.data,
    warnings: response.warnings ?? [],
  }
}

export async function listSuppliers(params: ListSuppliersParams = {}): Promise<{
  data: Supplier[]
  meta: SuppliersListMeta
}> {
  const response = await apiGet<Supplier[]>(`/api/v1/suppliers${toQuery(params)}`)
  return {
    data: response.data,
    meta: response.meta as unknown as SuppliersListMeta,
  }
}

export async function getSupplier(id: number): Promise<Supplier> {
  const response = await apiGet<Supplier>(`/api/v1/suppliers/${id}`)
  return response.data
}

export async function createSupplier(payload: CreateSupplierPayload): Promise<SupplierMutationResult> {
  const response = (await apiPost<Supplier>('/api/v1/suppliers', payload)) as SuccessWithWarnings<Supplier>
  return withWarnings(response)
}

export async function updateSupplier(
  id: number,
  payload: UpdateSupplierPayload,
): Promise<SupplierMutationResult> {
  const response = (await apiPatch<Supplier>(
    `/api/v1/suppliers/${id}`,
    payload,
  )) as SuccessWithWarnings<Supplier>
  return withWarnings(response)
}

export async function deleteSupplier(id: number): Promise<void> {
  await apiDelete(`/api/v1/suppliers/${id}`)
}

export async function transitionSupplier(
  id: number,
  payload: SupplierTransitionPayload,
): Promise<Supplier> {
  const response = await apiPost<Supplier>(`/api/v1/suppliers/${id}/transition`, payload)
  return response.data
}

export async function getSupplierEligibility(id: number): Promise<SupplierEligibility> {
  const response = await apiGet<SupplierEligibility>(`/api/v1/suppliers/${id}/eligibility`)
  return response.data
}

export async function listSupplierContacts(supplierId: number): Promise<SupplierContact[]> {
  const response = await apiGet<SupplierContact[]>(`/api/v1/suppliers/${supplierId}/contacts`)
  return response.data
}

export async function createSupplierContact(
  supplierId: number,
  payload: CreateSupplierContactPayload,
): Promise<SupplierContact> {
  const response = await apiPost<SupplierContact>(
    `/api/v1/suppliers/${supplierId}/contacts`,
    payload,
  )
  return response.data
}

export async function updateSupplierContact(
  supplierId: number,
  contactId: number,
  payload: UpdateSupplierContactPayload,
): Promise<SupplierContact> {
  const response = await apiPatch<SupplierContact>(
    `/api/v1/suppliers/${supplierId}/contacts/${contactId}`,
    payload,
  )
  return response.data
}

export async function deleteSupplierContact(supplierId: number, contactId: number): Promise<void> {
  await apiDelete(`/api/v1/suppliers/${supplierId}/contacts/${contactId}`)
}

export async function listSupplierCoverages(supplierId: number): Promise<SupplierCoverage[]> {
  const response = await apiGet<SupplierCoverage[]>(`/api/v1/suppliers/${supplierId}/coverages`)
  return response.data
}

export async function addSupplierCoverage(
  supplierId: number,
  payload: AddSupplierCoveragePayload,
): Promise<SupplierCoverage> {
  const response = await apiPost<SupplierCoverage>(
    `/api/v1/suppliers/${supplierId}/coverages`,
    payload,
  )
  return response.data
}

export async function deleteSupplierCoverage(
  supplierId: number,
  coverageId: number,
): Promise<void> {
  await apiDelete(`/api/v1/suppliers/${supplierId}/coverages/${coverageId}`)
}

export interface ListGeoPlacesParams {
  country_code?: string
  kind?: string
  parent_id?: number
  q?: string
  page?: number
  per_page?: number
}

export interface GeoPlacesListMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export async function listGeoPlaces(params: ListGeoPlacesParams = {}): Promise<{
  data: GeoPlace[]
  meta: GeoPlacesListMeta
}> {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, String(value))
  })
  const qs = query.toString()
  const response = await apiGet<GeoPlace[]>(`/api/v1/geo-places${qs ? `?${qs}` : ''}`)
  return {
    data: response.data,
    meta: response.meta as unknown as GeoPlacesListMeta,
  }
}
