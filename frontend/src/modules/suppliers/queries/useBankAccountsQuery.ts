import { computed, type MaybeRefOrGetter, toValue } from 'vue'
import { useQuery } from '@tanstack/vue-query'

import { getSupplierBankAccount, listSupplierBankAccounts } from '../api/bankAccountsApi'

export function supplierBankAccountsQueryKey(supplierId: number) {
  return ['suppliers', supplierId, 'bank-accounts'] as const
}

export function supplierBankAccountQueryKey(supplierId: number, accountId: number) {
  return ['suppliers', supplierId, 'bank-accounts', accountId] as const
}

export function useSupplierBankAccountsQuery(supplierId: MaybeRefOrGetter<number | null>) {
  return useQuery({
    queryKey: computed(() => {
      const id = toValue(supplierId)
      return id != null ? supplierBankAccountsQueryKey(id) : ['suppliers', 'bank-accounts', 'none']
    }),
    queryFn: () => {
      const id = toValue(supplierId)
      if (id == null) throw new Error('missing supplier id')
      return listSupplierBankAccounts(id)
    },
    enabled: computed(() => toValue(supplierId) != null),
  })
}

export function useSupplierBankAccountQuery(
  supplierId: MaybeRefOrGetter<number | null>,
  accountId: MaybeRefOrGetter<number | null>,
) {
  return useQuery({
    queryKey: computed(() => {
      const sid = toValue(supplierId)
      const aid = toValue(accountId)
      return sid != null && aid != null
        ? supplierBankAccountQueryKey(sid, aid)
        : ['suppliers', 'bank-accounts', 'detail', 'none']
    }),
    queryFn: () => {
      const sid = toValue(supplierId)
      const aid = toValue(accountId)
      if (sid == null || aid == null) throw new Error('missing supplier or bank account id')
      return getSupplierBankAccount(sid, aid)
    },
    enabled: computed(() => toValue(supplierId) != null && toValue(accountId) != null),
  })
}
