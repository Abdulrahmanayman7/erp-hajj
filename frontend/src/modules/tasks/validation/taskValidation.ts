import type { Task, TaskFormState, TaskPriority, TaskStatus } from '../types/tasks'

export const TASK_STATUSES: TaskStatus[] = ['draft', 'assigned', 'in_progress', 'completed', 'cancelled']
export const TASK_PRIORITIES: TaskPriority[] = ['low', 'medium', 'high']

export function validateTaskForm(form: TaskFormState): Record<string, string> {
  const errors: Record<string, string> = {}
  if (!form.title.trim()) errors.title = 'required'
  if (form.start_date && form.due_date && form.due_date < form.start_date) errors.due_date = 'dateRange'
  return errors
}

export function taskStatusBadgeClass(status: TaskStatus): string {
  return ({
    draft: 'bg-slate-100 text-brand-text ring-1 ring-inset ring-slate-300/80',
    assigned: 'bg-sky-100 text-brand-text ring-1 ring-inset ring-sky-300/80',
    in_progress: 'bg-amber-100 text-brand-text ring-1 ring-inset ring-amber-300/80',
    completed: 'bg-emerald-100 text-brand-text ring-1 ring-inset ring-emerald-300/80',
    cancelled: 'bg-red-100 text-brand-text ring-1 ring-inset ring-red-300/80',
  } as const)[status]
}

export function taskStatusDotClass(status: TaskStatus): string {
  return ({
    draft: 'bg-slate-500',
    assigned: 'bg-sky-500',
    in_progress: 'bg-amber-500',
    completed: 'bg-emerald-500',
    cancelled: 'bg-red-500',
  } as const)[status]
}

export function taskPriorityBadgeClass(priority: TaskPriority): string {
  return ({
    low: 'bg-neutral-100 text-brand-text ring-1 ring-neutral-200/80',
    medium: 'bg-sky-50 text-brand-text ring-1 ring-sky-200/70',
    high: 'bg-red-50 text-brand-text ring-1 ring-red-200/70',
  } as const)[priority]
}

export interface SelfServiceActions { canStart: boolean; canProgress: boolean; canComplete: boolean }

export function canSelfServiceActions(task: Task, linkedEmployeeId: number | null, can: (permission: string) => boolean): SelfServiceActions {
  const isSelf = linkedEmployeeId != null && task.assigned_to_employee_id === linkedEmployeeId
  const canManage = can('tasks.change_status')
  const canFinish = can('tasks.complete')
  return {
    canStart: task.status === 'assigned' && (canManage || isSelf),
    canProgress: (task.status === 'assigned' || task.status === 'in_progress') && (canManage || isSelf),
    canComplete: task.status === 'in_progress' && (canFinish || isSelf),
  }
}

export function resolveTasksListState(options: { isLoading: boolean; isError: boolean; count: number }): 'loading' | 'error' | 'empty' | 'ready' {
  if (options.isLoading) return 'loading'
  if (options.isError) return 'error'
  return options.count === 0 ? 'empty' : 'ready'
}
