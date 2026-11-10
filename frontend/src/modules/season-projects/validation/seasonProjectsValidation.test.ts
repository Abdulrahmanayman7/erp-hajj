import { describe, expect, it } from 'vitest'

import {
  canActivateProject,
  canSoftDeleteProject,
  computeProgressLagMetric,
  formatMoneyDisplay,
  isTaskMutationFrozenForCompletedProject,
  mapSeasonProjectErrorCode,
  MAX_BASELINE_CHECKPOINTS,
  resolveListState,
  validateBaselineCheckpointForm,
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

  it('validates baseline checkpoints strictly and bounds count', () => {
    expect(validateBaselineCheckpointForm([])).toEqual({ checkpoints: 'checkpointsRequired' })
    expect(
      validateBaselineCheckpointForm([
        { effective_at: '2026-11-01T10:00', planned_progress_percent: '50' },
        { effective_at: '2026-11-02T10:00', planned_progress_percent: '40' },
      ]),
    ).toEqual({ checkpoints: 'checkpointsNotNonDecreasing' })
    expect(
      validateBaselineCheckpointForm([
        { effective_at: '2026-11-01T10:00', planned_progress_percent: '101' },
      ])[`checkpoints.0.planned_progress_percent`],
    ).toBe('percentRange')

    const tooMany = Array.from({ length: MAX_BASELINE_CHECKPOINTS + 1 }, (_, i) => ({
      effective_at: `2026-01-${String((i % 28) + 1).padStart(2, '0')}T10:00`,
      planned_progress_percent: String(Math.min(100, i)),
    }))
    // Force unique ISO-ish stamps that still exceed max count.
    const uniqueTooMany = tooMany.map((row, i) => ({
      ...row,
      effective_at: `2026-01-01T${String(Math.floor(i / 60)).padStart(2, '0')}:${String(i % 60).padStart(2, '0')}`,
    }))
    expect(validateBaselineCheckpointForm(uniqueTooMany)).toEqual({
      checkpoints: 'checkpointsTooMany',
    })
  })

  it('computes planned lag metric and unavailable states', () => {
    expect(computeProgressLagMetric([], 10).unavailable_reason).toBe('no_approved_baseline')

    const now = new Date('2026-11-10T12:00:00.000Z')
    const baselines = [
      {
        id: 1,
        version: 1,
        status: 'approved' as const,
        approved_at: now.toISOString(),
        approved_by: 9,
        checkpoints: [
          { effective_at: '2026-11-09T12:00:00.000Z', planned_progress_percent: 40 },
        ],
      },
    ]
    const lag = computeProgressLagMetric(baselines, 30, now)
    expect(lag.state).toBe('available')
    expect(lag.lag_percentage_points).toBe(10)

    const futureOnly = computeProgressLagMetric(
      [
        {
          ...baselines[0],
          checkpoints: [
            { effective_at: '2026-11-11T12:00:00.000Z', planned_progress_percent: 40 },
          ],
        },
      ],
      30,
      now,
    )
    expect(futureOnly.unavailable_reason).toBe('no_checkpoint_at_evaluation_time')
  })
})
