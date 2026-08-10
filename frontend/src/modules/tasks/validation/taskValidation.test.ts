import { describe, expect, it } from 'vitest'
import type { Task } from '../types/tasks'
import {
  canSelfServiceActions,
  resolveTasksListState,
  taskPriorityBadgeClass,
  taskStatusBadgeClass,
  validateTaskForm,
} from './taskValidation'

function makeTask(overrides: Partial<Task> = {}): Task {
  return {
    id: 1, task_number: 'TSK-000001', title: 'مهمة', description: null, notes: null,
    status: 'assigned', priority: 'medium', progress_percent: 0,
    decision_id: null, organization_unit_id: null, assigned_to_employee_id: 5,
    start_date: null, due_date: null, completed_at: null, completion_notes: null,
    is_overdue: false, decision: null, organization_unit: null, assigned_to_employee: null,
    created_by: null, created_at: null, updated_at: null,
    ...overrides,
  }
}

describe('task UI rules', () => {
  it('requires a title', () => {
    expect(validateTaskForm({ title: '', description: '', notes: '', priority: 'medium', decision_id: '', organization_unit_id: '', assigned_to_employee_id: '', start_date: '', due_date: '' })).toMatchObject({ title: 'required' })
  })

  it('rejects a due date before the start date', () => {
    const errors = validateTaskForm({ title: 'عنوان', description: '', notes: '', priority: 'medium', decision_id: '', organization_unit_id: '', assigned_to_employee_id: '', start_date: '2026-02-10', due_date: '2026-02-01' })
    expect(errors.due_date).toBe('dateRange')
  })

  it('accepts a valid date range', () => {
    const errors = validateTaskForm({ title: 'عنوان', description: '', notes: '', priority: 'medium', decision_id: '', organization_unit_id: '', assigned_to_employee_id: '', start_date: '2026-02-01', due_date: '2026-02-10' })
    expect(errors.due_date).toBeUndefined()
  })

  it('resolves list states', () => {
    expect(resolveTasksListState({ isLoading: true, isError: false, count: 0 })).toBe('loading')
    expect(resolveTasksListState({ isLoading: false, isError: true, count: 0 })).toBe('error')
    expect(resolveTasksListState({ isLoading: false, isError: false, count: 0 })).toBe('empty')
    expect(resolveTasksListState({ isLoading: false, isError: false, count: 3 })).toBe('ready')
  })

  it('maps status and priority to badge classes', () => {
    expect(taskStatusBadgeClass('draft')).toContain('neutral')
    expect(taskStatusBadgeClass('completed')).toContain('emerald')
    expect(taskPriorityBadgeClass('high')).toContain('red')
  })

  it('grants self-service actions to the matched assignee even without manager permissions', () => {
    const task = makeTask({ status: 'assigned', assigned_to_employee_id: 5 })
    const result = canSelfServiceActions(task, 5, () => false)
    expect(result).toEqual({ canStart: true, canProgress: true, canComplete: false })
  })

  it('denies self-service actions when the linked employee does not match', () => {
    const task = makeTask({ status: 'assigned', assigned_to_employee_id: 5 })
    const result = canSelfServiceActions(task, 9, () => false)
    expect(result).toEqual({ canStart: false, canProgress: false, canComplete: false })
  })

  it('denies self-service actions when there is no linked employee', () => {
    const task = makeTask({ status: 'in_progress', assigned_to_employee_id: 5 })
    const result = canSelfServiceActions(task, null, () => false)
    expect(result.canComplete).toBe(false)
  })

  it('still grants actions via manager permission regardless of assignee', () => {
    const task = makeTask({ status: 'in_progress', assigned_to_employee_id: 5 })
    const result = canSelfServiceActions(task, null, (permission) => permission === 'tasks.complete')
    expect(result.canComplete).toBe(true)
  })
})
