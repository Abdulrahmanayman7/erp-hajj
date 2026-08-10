import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { decisionDetailQueryKey, decisionsQueryKey } from '@/modules/decisions/queries/useDecisionsQuery'
import {
  assignTask,
  cancelTask,
  completeTask,
  createTask,
  deleteTask,
  startTask,
  updateTask,
  updateTaskProgress,
} from '../api/tasksApi'
import { taskDetailQueryKey, tasksQueryKey } from '../queries/useTasksQuery'
import type { Task, TaskPayload } from '../types/tasks'

async function invalidate(client: ReturnType<typeof useQueryClient>, id?: number, decisionId?: number | null): Promise<void> {
  await client.invalidateQueries({ queryKey: tasksQueryKey })
  if (id) await client.invalidateQueries({ queryKey: taskDetailQueryKey(id) })
  if (decisionId) {
    await client.invalidateQueries({ queryKey: decisionsQueryKey })
    await client.invalidateQueries({ queryKey: decisionDetailQueryKey(decisionId) })
  }
}

export function useCreateTaskMutation() {
  const client = useQueryClient()
  return useMutation({ mutationFn: createTask, onSuccess: (task: Task) => invalidate(client, task.id, task.decision_id) })
}
export function useUpdateTaskMutation() {
  const client = useQueryClient()
  return useMutation({ mutationFn: ({ id, payload }: { id: number; payload: TaskPayload }) => updateTask(id, payload), onSuccess: (task: Task) => invalidate(client, task.id, task.decision_id) })
}
export function useDeleteTaskMutation() {
  const client = useQueryClient()
  return useMutation({ mutationFn: ({ id }: { id: number; decisionId?: number | null }) => deleteTask(id), onSuccess: (_, vars) => invalidate(client, vars.id, vars.decisionId) })
}
export function useAssignTaskMutation() {
  const client = useQueryClient()
  return useMutation({ mutationFn: ({ id, assignedToEmployeeId, comment }: { id: number; assignedToEmployeeId: number; comment?: string }) => assignTask(id, assignedToEmployeeId, comment), onSuccess: (task: Task) => invalidate(client, task.id, task.decision_id) })
}
export function useStartTaskMutation() {
  const client = useQueryClient()
  return useMutation({ mutationFn: ({ id, comment }: { id: number; comment?: string }) => startTask(id, comment), onSuccess: (task: Task) => invalidate(client, task.id, task.decision_id) })
}
export function useUpdateTaskProgressMutation() {
  const client = useQueryClient()
  return useMutation({ mutationFn: ({ id, progressPercent }: { id: number; progressPercent: number }) => updateTaskProgress(id, progressPercent), onSuccess: (task: Task) => invalidate(client, task.id, task.decision_id) })
}
export function useCompleteTaskMutation() {
  const client = useQueryClient()
  return useMutation({ mutationFn: ({ id, completionNotes }: { id: number; completionNotes: string }) => completeTask(id, completionNotes), onSuccess: (task: Task) => invalidate(client, task.id, task.decision_id) })
}
export function useCancelTaskMutation() {
  const client = useQueryClient()
  return useMutation({ mutationFn: ({ id, comment }: { id: number; comment: string }) => cancelTask(id, comment), onSuccess: (task: Task) => invalidate(client, task.id, task.decision_id) })
}
