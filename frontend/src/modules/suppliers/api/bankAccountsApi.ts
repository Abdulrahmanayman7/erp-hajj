import { apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type {
  BankAccountRevealPayload,
  CreateBankAccountPayload,
  SupplierBankAccount,
  SupersedeBankAccountPayload,
  UpdatePendingBankAccountPayload,
} from '../types/banking'

function base(supplierId: number): string {
  return `/api/v1/suppliers/${supplierId}/bank-accounts`
}

function accountPath(supplierId: number, accountId: number): string {
  return `${base(supplierId)}/${accountId}`
}

/** Strip any accidental sensitive client fields before send. */
function sanitizeWritePayload(
  payload: CreateBankAccountPayload | UpdatePendingBankAccountPayload | SupersedeBankAccountPayload,
): CreateBankAccountPayload {
  const iban = payload.iban?.trim() || null
  const accountNumber = payload.account_number?.trim() || null
  const label = payload.label?.trim() || null
  const evidence =
    payload.evidence_document_id != null && Number(payload.evidence_document_id) > 0
      ? Number(payload.evidence_document_id)
      : null

  return {
    account_holder_name: payload.account_holder_name.trim(),
    bank_name: payload.bank_name.trim(),
    label,
    iban,
    account_number: accountNumber,
    currency: (payload.currency ?? 'SAR').trim().toUpperCase() || 'SAR',
    evidence_document_id: evidence,
  }
}

export async function listSupplierBankAccounts(supplierId: number): Promise<SupplierBankAccount[]> {
  const response = await apiGet<SupplierBankAccount[]>(base(supplierId))
  return response.data
}

export async function getSupplierBankAccount(
  supplierId: number,
  accountId: number,
): Promise<SupplierBankAccount> {
  const response = await apiGet<SupplierBankAccount>(accountPath(supplierId, accountId))
  return response.data
}

export async function createSupplierBankAccount(
  supplierId: number,
  payload: CreateBankAccountPayload,
): Promise<SupplierBankAccount> {
  const response = await apiPost<SupplierBankAccount>(base(supplierId), sanitizeWritePayload(payload))
  return response.data
}

export async function updatePendingSupplierBankAccount(
  supplierId: number,
  accountId: number,
  payload: UpdatePendingBankAccountPayload,
): Promise<SupplierBankAccount> {
  const response = await apiPatch<SupplierBankAccount>(
    accountPath(supplierId, accountId),
    sanitizeWritePayload(payload),
  )
  return response.data
}

export async function verifySupplierBankAccount(
  supplierId: number,
  accountId: number,
): Promise<SupplierBankAccount> {
  const response = await apiPost<SupplierBankAccount>(`${accountPath(supplierId, accountId)}/verify`, {})
  return response.data
}

export async function rejectSupplierBankAccount(
  supplierId: number,
  accountId: number,
  reason: string,
): Promise<SupplierBankAccount> {
  const response = await apiPost<SupplierBankAccount>(`${accountPath(supplierId, accountId)}/reject`, {
    reason: reason.trim(),
  })
  return response.data
}

export async function revokeSupplierBankAccount(
  supplierId: number,
  accountId: number,
  reason: string,
): Promise<SupplierBankAccount> {
  const response = await apiPost<SupplierBankAccount>(`${accountPath(supplierId, accountId)}/revoke`, {
    reason: reason.trim(),
  })
  return response.data
}

export async function supersedeSupplierBankAccount(
  supplierId: number,
  accountId: number,
  payload: SupersedeBankAccountPayload,
): Promise<SupplierBankAccount> {
  const response = await apiPost<SupplierBankAccount>(
    `${accountPath(supplierId, accountId)}/supersede`,
    sanitizeWritePayload(payload),
  )
  return response.data
}

export async function setPrimarySupplierBankAccount(
  supplierId: number,
  accountId: number,
): Promise<SupplierBankAccount> {
  const response = await apiPost<SupplierBankAccount>(
    `${accountPath(supplierId, accountId)}/set-primary`,
    {},
  )
  return response.data
}

export async function clearPrimarySupplierBankAccount(
  supplierId: number,
  accountId: number,
): Promise<SupplierBankAccount> {
  const response = await apiPost<SupplierBankAccount>(
    `${accountPath(supplierId, accountId)}/clear-primary`,
    {},
  )
  return response.data
}

/**
 * Audited reveal — returns plaintext identifiers for ephemeral UI only.
 * Callers must not put the result in query caches, storage, URLs, or logs.
 */
export async function revealSupplierBankAccount(
  supplierId: number,
  accountId: number,
  reason: string,
): Promise<BankAccountRevealPayload> {
  const response = await apiPost<BankAccountRevealPayload>(
    `${accountPath(supplierId, accountId)}/reveal`,
    { reason: reason.trim() },
  )
  return response.data
}
