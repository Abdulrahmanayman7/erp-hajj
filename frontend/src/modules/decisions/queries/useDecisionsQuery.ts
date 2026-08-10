import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'
import { getDecision, listDecisions } from '../api/decisionsApi'
import type { ListDecisionsParams } from '../types/decisions'

export const decisionsQueryKey = ['decisions'] as const
export const decisionDetailQueryKey = (id: number) => [...decisionsQueryKey, 'detail', id] as const
export function useDecisionsQuery(params: MaybeRefOrGetter<ListDecisionsParams>) {
  return useQuery({ queryKey: computed(() => [...decisionsQueryKey, 'list', toValue(params)]), queryFn: () => listDecisions(toValue(params)), placeholderData: keepPreviousData })
}
export function useDecisionQuery(id: MaybeRefOrGetter<number | null>) {
  return useQuery({
    queryKey: computed(() => toValue(id) == null ? [...decisionsQueryKey, 'detail', 'unknown'] : decisionDetailQueryKey(toValue(id)!)),
    queryFn: () => getDecision(toValue(id)!),
    enabled: computed(() => { const value = toValue(id); return value != null && Number.isFinite(value) && value > 0 }),
  })
}
