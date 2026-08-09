import { apiDelete, apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type {
  Contract,
  ContractTransitionPayload,
  ContractsListMeta,
  CreateContractPayload,
  ListContractsParams,
  RenewContractResult,
  UpdateContractPayload,
} from '../types/contracts'

function toQuery(params: ListContractsParams): string {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') {
      return
    }
    if (typeof value === 'boolean') {
      query.set(key, value ? '1' : '0')
      return
    }
    query.set(key, String(value))
  })
  const qs = query.toString()
  return qs ? `?${qs}` : ''
}

export async function listContracts(params: ListContractsParams = {}): Promise<{
  data: Contract[]
  meta: ContractsListMeta
}> {
  const response = await apiGet<Contract[]>(`/api/v1/contracts${toQuery(params)}`)
  return {
    data: response.data,
    meta: response.meta as unknown as ContractsListMeta,
  }
}

export async function getContract(id: number): Promise<Contract> {
  const response = await apiGet<Contract>(`/api/v1/contracts/${id}`)
  return response.data
}

export async function createContract(payload: CreateContractPayload): Promise<Contract> {
  const response = await apiPost<Contract>('/api/v1/contracts', payload)
  return response.data
}

export async function updateContract(
  id: number,
  payload: UpdateContractPayload,
): Promise<Contract> {
  const response = await apiPatch<Contract>(`/api/v1/contracts/${id}`, payload)
  return response.data
}

export async function deleteContract(id: number): Promise<void> {
  await apiDelete(`/api/v1/contracts/${id}`)
}

export async function submitContractForReview(
  id: number,
  payload: ContractTransitionPayload = {},
): Promise<Contract> {
  const response = await apiPost<Contract>(`/api/v1/contracts/${id}/submit-review`, payload)
  return response.data
}

export async function returnContractToDraft(
  id: number,
  payload: ContractTransitionPayload,
): Promise<Contract> {
  const response = await apiPost<Contract>(`/api/v1/contracts/${id}/return-draft`, payload)
  return response.data
}

export async function approveContract(
  id: number,
  payload: ContractTransitionPayload = {},
): Promise<Contract> {
  const response = await apiPost<Contract>(`/api/v1/contracts/${id}/approve`, payload)
  return response.data
}

export async function signContract(
  id: number,
  payload: ContractTransitionPayload = {},
): Promise<Contract> {
  const response = await apiPost<Contract>(`/api/v1/contracts/${id}/sign`, payload)
  return response.data
}

export async function executeContract(
  id: number,
  payload: ContractTransitionPayload = {},
): Promise<Contract> {
  const response = await apiPost<Contract>(`/api/v1/contracts/${id}/execute`, payload)
  return response.data
}

export async function closeContract(
  id: number,
  payload: ContractTransitionPayload = {},
): Promise<Contract> {
  const response = await apiPost<Contract>(`/api/v1/contracts/${id}/close`, payload)
  return response.data
}

export async function cancelContract(
  id: number,
  payload: ContractTransitionPayload,
): Promise<Contract> {
  const response = await apiPost<Contract>(`/api/v1/contracts/${id}/cancel`, payload)
  return response.data
}

export async function renewContract(
  id: number,
  payload: ContractTransitionPayload = {},
): Promise<RenewContractResult> {
  const response = await apiPost<RenewContractResult>(`/api/v1/contracts/${id}/renew`, payload)
  return response.data
}
