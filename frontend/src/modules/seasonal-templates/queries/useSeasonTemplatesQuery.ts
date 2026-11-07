import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { getSeasonTemplate, listSeasonTemplates } from '../api/seasonTemplatesApi'
import type { ListSeasonTemplatesParams } from '../types/seasonTemplates'

export const seasonTemplatesQueryKey = ['season-templates'] as const
export const seasonTemplateDetailQueryKey = (id: number) =>
  [...seasonTemplatesQueryKey, 'detail', id] as const

export function useSeasonTemplatesQuery(
  params: MaybeRefOrGetter<ListSeasonTemplatesParams>,
  enabled: MaybeRefOrGetter<boolean> = true,
) {
  return useQuery({
    queryKey: computed(() => [...seasonTemplatesQueryKey, 'list', toValue(params)]),
    queryFn: () => listSeasonTemplates(toValue(params)),
    placeholderData: keepPreviousData,
    enabled: computed(() => toValue(enabled)),
  })
}

export function useSeasonTemplateQuery(id: MaybeRefOrGetter<number | null>) {
  return useQuery({
    queryKey: computed(() =>
      toValue(id) == null
        ? [...seasonTemplatesQueryKey, 'detail', 'unknown']
        : seasonTemplateDetailQueryKey(toValue(id)!),
    ),
    queryFn: () => getSeasonTemplate(toValue(id)!),
    enabled: computed(() => {
      const value = toValue(id)
      return value != null && Number.isFinite(value) && value > 0
    }),
  })
}
