import { apiDelete, apiGet, apiPatch, apiPost, apiPut } from '@/shared/api/http'
import type { ListTasksParams, Task, TaskPayload, TasksListMeta } from '../types/tasks'

function query(params: ListTasksParams): string {
  const value = new URLSearchParams()
  Object.entries(params).forEach(([key, item]) => {
    if (item === undefined || item === null || item === '') return
    value.set(key, typeof item === 'boolean' ? (item ? '1' : '0') : String(item))
  })
  return value.size ? `?${value}` : ''
}

export async function listTasks(params: ListTasksParams = {}): Promise<{ data: Task[]; meta: TasksListMeta }> {
  const response = await apiGet<Task[]>(`/api/v1/tasks${query(params)}`)
  return { data: response.data, meta: response.meta as unknown as TasksListMeta }
}
export async function getTask(id: number): Promise<Task> { return (await apiGet<Task>(`/api/v1/tasks/${id}`)).data }
export async function createTask(payload: TaskPayload): Promise<Task> { return (await apiPost<Task>('/api/v1/tasks', payload)).data }
export async function updateTask(id: number, payload: TaskPayload): Promise<Task> { return (await apiPatch<Task>(`/api/v1/tasks/${id}`, payload)).data }
export async function deleteTask(id: number): Promise<void> { await apiDelete(`/api/v1/tasks/${id}`) }
export async function assignTask(id: number, assignedToEmployeeId: number, comment?: string): Promise<Task> {
  return (await apiPut<Task>(`/api/v1/tasks/${id}/assignee`, { assigned_to_employee_id: assignedToEmployeeId, comment: comment || undefined })).data
}
export async function startTask(id: number, comment?: string): Promise<Task> { return (await apiPost<Task>(`/api/v1/tasks/${id}/start`, { comment: comment || undefined })).data }
export async function updateTaskProgress(id: number, progressPercent: number): Promise<Task> {
  return (await apiPut<Task>(`/api/v1/tasks/${id}/progress`, { progress_percent: progressPercent })).data
}
export async function completeTask(id: number, completionNotes: string): Promise<Task> {
  return (await apiPost<Task>(`/api/v1/tasks/${id}/complete`, { completion_notes: completionNotes })).data
}
export async function cancelTask(id: number, comment: string): Promise<Task> { return (await apiPost<Task>(`/api/v1/tasks/${id}/cancel`, { comment })).data }
