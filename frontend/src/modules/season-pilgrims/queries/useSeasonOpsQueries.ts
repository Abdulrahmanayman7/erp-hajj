import { useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { listFamilyGroups, getFamilyGroup } from '../api/familyGroupsApi'
import { listPilgrimGroups } from '../api/pilgrimGroupsApi'
import { listSeasonPrograms } from '../api/programsApi'

export const pilgrimGroupsQueryKey = ['season-pilgrim-groups'] as const
export const seasonProgramsQueryKey = ['season-programs'] as const
export const familyGroupsQueryKey = ['season-family-groups'] as const

export function usePilgrimGroupsQuery(
  seasonId: MaybeRefOrGetter<number | null | undefined>,
  enabled: MaybeRefOrGetter<boolean> = true,
) {
  return useQuery({
    queryKey: computed(() => [...pilgrimGroupsQueryKey, toValue(seasonId) ?? 'none']),
    queryFn: () => listPilgrimGroups(toValue(seasonId)),
    enabled: computed(() => Boolean(toValue(enabled)) && Number(toValue(seasonId)) > 0),
  })
}

export function useSeasonProgramsQuery(
  seasonId: MaybeRefOrGetter<number | null | undefined>,
  enabled: MaybeRefOrGetter<boolean> = true,
) {
  return useQuery({
    queryKey: computed(() => [...seasonProgramsQueryKey, toValue(seasonId) ?? 'none']),
    queryFn: () => listSeasonPrograms(toValue(seasonId)),
    enabled: computed(() => Boolean(toValue(enabled)) && Number(toValue(seasonId)) > 0),
  })
}

export function useFamilyGroupsQuery(
  seasonId: MaybeRefOrGetter<number | null | undefined>,
  enabled: MaybeRefOrGetter<boolean> = true,
) {
  return useQuery({
    queryKey: computed(() => [...familyGroupsQueryKey, toValue(seasonId) ?? 'none']),
    queryFn: () => listFamilyGroups(toValue(seasonId)),
    enabled: computed(() => Boolean(toValue(enabled)) && Number(toValue(seasonId)) > 0),
  })
}

export function useFamilyGroupDetailQuery(
  id: MaybeRefOrGetter<number | null>,
  seasonId: MaybeRefOrGetter<number | null | undefined>,
  enabled: MaybeRefOrGetter<boolean> = true,
) {
  return useQuery({
    queryKey: computed(() => [
      ...familyGroupsQueryKey,
      'detail',
      toValue(id),
      toValue(seasonId) ?? 'none',
    ]),
    queryFn: () => getFamilyGroup(toValue(id)!, toValue(seasonId)),
    enabled: computed(
      () =>
        Boolean(toValue(enabled)) &&
        Number(toValue(id)) > 0 &&
        Number(toValue(seasonId)) > 0,
    ),
  })
}
