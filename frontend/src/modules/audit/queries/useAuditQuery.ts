import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { getAuditLog, listAuditLogs } from '../api/auditApi'
import type { ListAuditLogsParams } from '../types/audit'

export function useAuditLogsQuery(params: MaybeRefOrGetter<ListAuditLogsParams>) {
  return useQuery({
    queryKey: computed(() => ['audit-logs', toValue(params)]),
    queryFn: () => listAuditLogs(toValue(params)),
    placeholderData: keepPreviousData,
  })
}

export function useAuditLogQuery(id: MaybeRefOrGetter<number | null>) {
  return useQuery({
    queryKey: computed(() => ['audit-logs', 'detail', toValue(id)]),
    queryFn: () => getAuditLog(toValue(id)!),
    enabled: computed(() => {
      const value = toValue(id)
      return typeof value === 'number' && value > 0
    }),
  })
}
