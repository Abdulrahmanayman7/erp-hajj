import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  clearPrimarySupplierBankAccount,
  createSupplierBankAccount,
  rejectSupplierBankAccount,
  revokeSupplierBankAccount,
  setPrimarySupplierBankAccount,
  supersedeSupplierBankAccount,
  updatePendingSupplierBankAccount,
  verifySupplierBankAccount,
  revealSupplierBankAccount,
} from '../api/bankAccountsApi'
import { supplierBankAccountsQueryKey } from '../queries/useBankAccountsQuery'
import type {
  CreateBankAccountPayload,
  SupersedeBankAccountPayload,
  UpdatePendingBankAccountPayload,
} from '../types/banking'

type Client = ReturnType<typeof useQueryClient>

async function invalidateBankAccounts(client: Client, supplierId: number): Promise<void> {
  await client.invalidateQueries({ queryKey: supplierBankAccountsQueryKey(supplierId) })
}

export function useCreateBankAccountMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      supplierId,
      payload,
    }: {
      supplierId: number
      payload: CreateBankAccountPayload
    }) => createSupplierBankAccount(supplierId, payload),
    onSuccess: (_data, vars) => invalidateBankAccounts(client, vars.supplierId),
  })
}

export function useUpdatePendingBankAccountMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      supplierId,
      accountId,
      payload,
    }: {
      supplierId: number
      accountId: number
      payload: UpdatePendingBankAccountPayload
    }) => updatePendingSupplierBankAccount(supplierId, accountId, payload),
    onSuccess: (_data, vars) => invalidateBankAccounts(client, vars.supplierId),
  })
}

export function useVerifyBankAccountMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ supplierId, accountId }: { supplierId: number; accountId: number }) =>
      verifySupplierBankAccount(supplierId, accountId),
    onSuccess: (_data, vars) => invalidateBankAccounts(client, vars.supplierId),
  })
}

export function useRejectBankAccountMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      supplierId,
      accountId,
      reason,
    }: {
      supplierId: number
      accountId: number
      reason: string
    }) => rejectSupplierBankAccount(supplierId, accountId, reason),
    onSuccess: (_data, vars) => invalidateBankAccounts(client, vars.supplierId),
  })
}

export function useRevokeBankAccountMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      supplierId,
      accountId,
      reason,
    }: {
      supplierId: number
      accountId: number
      reason: string
    }) => revokeSupplierBankAccount(supplierId, accountId, reason),
    onSuccess: (_data, vars) => invalidateBankAccounts(client, vars.supplierId),
  })
}

export function useSupersedeBankAccountMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      supplierId,
      accountId,
      payload,
    }: {
      supplierId: number
      accountId: number
      payload: SupersedeBankAccountPayload
    }) => supersedeSupplierBankAccount(supplierId, accountId, payload),
    onSuccess: (_data, vars) => invalidateBankAccounts(client, vars.supplierId),
  })
}

export function useSetPrimaryBankAccountMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ supplierId, accountId }: { supplierId: number; accountId: number }) =>
      setPrimarySupplierBankAccount(supplierId, accountId),
    onSuccess: (_data, vars) => invalidateBankAccounts(client, vars.supplierId),
  })
}

export function useClearPrimaryBankAccountMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ supplierId, accountId }: { supplierId: number; accountId: number }) =>
      clearPrimarySupplierBankAccount(supplierId, accountId),
    onSuccess: (_data, vars) => invalidateBankAccounts(client, vars.supplierId),
  })
}

/**
 * Reveal mutation intentionally does NOT write into query cache.
 * gcTime 0 + caller reset keeps plaintext out of reusable caches.
 */
export function useRevealBankAccountMutation() {
  return useMutation({
    mutationFn: ({
      supplierId,
      accountId,
      reason,
    }: {
      supplierId: number
      accountId: number
      reason: string
    }) => revealSupplierBankAccount(supplierId, accountId, reason),
    gcTime: 0,
  })
}
