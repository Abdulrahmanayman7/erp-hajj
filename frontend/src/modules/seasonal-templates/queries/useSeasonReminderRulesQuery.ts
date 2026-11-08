import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { getSeasonReminderRule, listSeasonReminderRules } from '../api/seasonReminderRulesApi'
import type { ListSeasonReminderRulesParams } from '../types/seasonReminderRules'

export const seasonReminderRulesQueryKey = ['season-reminder-rules'] as const
export const seasonReminderRuleDetailQueryKey = (id: number) =>
  [...seasonReminderRulesQueryKey, 'detail', id] as const

export function useSeasonReminderRulesQuery(
  seasonId: MaybeRefOrGetter<number | null>,
  params: MaybeRefOrGetter<ListSeasonReminderRulesParams>,
) {
  return useQuery({
    queryKey: computed(() => [...seasonReminderRulesQueryKey, 'list', toValue(seasonId), toValue(params)]),
    queryFn: () => listSeasonReminderRules(toValue(seasonId)!, toValue(params)),
    placeholderData: keepPreviousData,
    enabled: computed(() => toValue(seasonId) != null),
  })
}

export function useSeasonReminderRuleQuery(id: MaybeRefOrGetter<number | null>) {
  return useQuery({
    queryKey: computed(() =>
      toValue(id) == null
        ? [...seasonReminderRulesQueryKey, 'detail', 'unknown']
        : seasonReminderRuleDetailQueryKey(toValue(id)!),
    ),
    queryFn: () => getSeasonReminderRule(toValue(id)!),
    enabled: computed(() => {
      const value = toValue(id)
      return value != null && Number.isFinite(value) && value > 0
    }),
  })
}
