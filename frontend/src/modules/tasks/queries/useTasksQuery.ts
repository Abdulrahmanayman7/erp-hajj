import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'
import { getTask, listTasks } from '../api/tasksApi'
import type { ListTasksParams } from '../types/tasks'

export const tasksQueryKey = ['tasks'] as const
export const taskDetailQueryKey = (id: number) => [...tasksQueryKey, 'detail', id] as const
export function useTasksQuery(params: MaybeRefOrGetter<ListTasksParams>) {
  return useQuery({ queryKey: computed(() => [...tasksQueryKey, 'list', toValue(params)]), queryFn: () => listTasks(toValue(params)), placeholderData: keepPreviousData })
}
export function useTaskQuery(id: MaybeRefOrGetter<number | null>) {
  return useQuery({
    queryKey: computed(() => toValue(id) == null ? [...tasksQueryKey, 'detail', 'unknown'] : taskDetailQueryKey(toValue(id)!)),
    queryFn: () => getTask(toValue(id)!),
    enabled: computed(() => { const value = toValue(id); return value != null && Number.isFinite(value) && value > 0 }),
  })
}
