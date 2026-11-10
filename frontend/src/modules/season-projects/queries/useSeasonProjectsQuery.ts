import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import {
  getSeasonProject,
  getSeasonProjectBudget,
  listSeasonProjectPhases,
  listSeasonProjectProgressBaselines,
  listSeasonProjects,
} from '../api/seasonProjectsApi'
import type { ListSeasonProjectsParams } from '../types/seasonProjects'

export const seasonProjectsQueryKey = ['season-projects'] as const

export function useSeasonProjectsQuery(
  params: MaybeRefOrGetter<ListSeasonProjectsParams>,
  enabled: MaybeRefOrGetter<boolean> = true,
) {
  return useQuery({
    queryKey: computed(() => [...seasonProjectsQueryKey, 'list', toValue(params)]),
    queryFn: () => listSeasonProjects(toValue(params)),
    enabled: computed(() => toValue(enabled)),
    placeholderData: keepPreviousData,
  })
}

export function useSeasonProjectQuery(
  id: MaybeRefOrGetter<number | null>,
  seasonId: MaybeRefOrGetter<number | null | undefined> = null,
  enabled: MaybeRefOrGetter<boolean> = true,
) {
  return useQuery({
    queryKey: computed(() => [...seasonProjectsQueryKey, 'detail', toValue(id), toValue(seasonId)]),
    queryFn: () => getSeasonProject(toValue(id) as number, toValue(seasonId)),
    enabled: computed(() => toValue(enabled) && toValue(id) != null),
  })
}

export function useSeasonProjectPhasesQuery(
  projectId: MaybeRefOrGetter<number | null>,
  seasonId: MaybeRefOrGetter<number | null | undefined> = null,
  enabled: MaybeRefOrGetter<boolean> = true,
) {
  return useQuery({
    queryKey: computed(() => [
      ...seasonProjectsQueryKey,
      'phases',
      toValue(projectId),
      toValue(seasonId),
    ]),
    queryFn: () => listSeasonProjectPhases(toValue(projectId) as number, toValue(seasonId)),
    enabled: computed(() => toValue(enabled) && toValue(projectId) != null),
  })
}

export function useSeasonProjectBudgetQuery(
  projectId: MaybeRefOrGetter<number | null>,
  seasonId: MaybeRefOrGetter<number | null | undefined> = null,
  enabled: MaybeRefOrGetter<boolean> = true,
) {
  return useQuery({
    queryKey: computed(() => [
      ...seasonProjectsQueryKey,
      'budget',
      toValue(projectId),
      toValue(seasonId),
    ]),
    queryFn: () => getSeasonProjectBudget(toValue(projectId) as number, toValue(seasonId)),
    enabled: computed(() => toValue(enabled) && toValue(projectId) != null),
  })
}

export function useSeasonProjectProgressBaselinesQuery(
  projectId: MaybeRefOrGetter<number | null>,
  seasonId: MaybeRefOrGetter<number | null | undefined> = null,
  enabled: MaybeRefOrGetter<boolean> = true,
) {
  return useQuery({
    queryKey: computed(() => [
      ...seasonProjectsQueryKey,
      'progress-baselines',
      toValue(projectId),
      toValue(seasonId),
    ]),
    queryFn: () =>
      listSeasonProjectProgressBaselines(toValue(projectId) as number, toValue(seasonId)),
    enabled: computed(() => toValue(enabled) && toValue(projectId) != null),
  })
}
