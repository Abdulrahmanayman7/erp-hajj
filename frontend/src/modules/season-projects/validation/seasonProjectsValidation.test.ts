import { describe, expect, it } from 'vitest'

import {
  canActivateProject,
  canSoftDeleteProject,
  formatMoneyDisplay,
  isTaskMutationFrozenForCompletedProject,
  mapSeasonProjectErrorCode,
  resolveListState,
  validateProjectForm,
} from './seasonProjectsValidation'

describe('seasonProjectsValidation', () => {
  it('resolveListState prioritizes need_season', () => {
    expect(
      resolveListState({ hasSeason: false, isLoading: true, isError: false, count: 0 }),
    ).toBe('need_season')
    expect(resolveListState({ isLoading: false, isError: false, count: 0 })).toBe('empty')
  })

  it('maps stable backend error codes', () => {
    expect(mapSeasonProjectErrorCode('SEASON_PROJECT_CANCEL_HAS_OPEN_TASKS')).toBe(
      'cancelHasOpenTasks',
    )
    expect(mapSeasonProjectErrorCode('SEASON_PROJECT_PO_CURRENCY_MISMATCH')).toBe(
      'poCurrencyMismatch',
    )
    expect(mapSeasonProjectErrorCode('SEASON_PROJECT_TASK_FROZEN')).toBe('taskFrozen')
    expect(mapSeasonProjectErrorCode('SEASON_PROJECT_PO_UNLINK_HAS_POSTED_RECEIPTS')).toBe(
      'poUnlinkHasPostedReceipts',
    )
    expect(mapSeasonProjectErrorCode(undefined)).toBe('generic')
  })

  it('never treats unavailable financials as zero', () => {
    expect(formatMoneyDisplay(null, false)).toBe('—')
    expect(formatMoneyDisplay('0.00', false)).toBe('—')
    expect(formatMoneyDisplay('12.50', true)).toBe('12.50')
  })

  it('gates draft delete and activation', () => {
    expect(canSoftDeleteProject('draft')).toBe(true)
    expect(canSoftDeleteProject('active')).toBe(false)
    expect(canActivateProject('draft')).toBe(true)
    expect(canActivateProject('active')).toBe(false)
  })

  it('freezes completed-project task mutations except complete/cancel', () => {
    expect(isTaskMutationFrozenForCompletedProject('completed', 'create')).toBe(true)
    expect(isTaskMutationFrozenForCompletedProject('completed', 'assign')).toBe(true)
    expect(isTaskMutationFrozenForCompletedProject('completed', 'complete')).toBe(false)
    expect(isTaskMutationFrozenForCompletedProject('completed', 'cancel')).toBe(false)
    expect(isTaskMutationFrozenForCompletedProject('active', 'create')).toBe(false)
  })

  it('requires title on project form', () => {
    expect(validateProjectForm({ title: '', description: '', owner_employee_id: '' })).toEqual({
      title: 'required',
    })
    expect(
      validateProjectForm({ title: 'مشروع', description: '', owner_employee_id: '' }),
    ).toEqual({})
  })
})
