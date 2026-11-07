import { describe, expect, it } from 'vitest'

import {
  canApproveRiskAssessment,
  emptyRiskForm,
  previewAssessment,
  resolveRisksListState,
  severityFromScore,
  validateRiskForm,
  validateTreatmentForm,
} from './riskValidation'

describe('riskValidation', () => {
  it('maps D02 severity bands from score', () => {
    expect(severityFromScore(1)).toBe('low')
    expect(severityFromScore(4)).toBe('low')
    expect(severityFromScore(5)).toBe('medium')
    expect(severityFromScore(9)).toBe('medium')
    expect(severityFromScore(10)).toBe('high')
    expect(severityFromScore(15)).toBe('high')
    expect(severityFromScore(16)).toBe('critical')
    expect(severityFromScore(25)).toBe('critical')
  })

  it('previews assessment score and severity for 5x5 matrix', () => {
    expect(previewAssessment(5, 5)).toEqual({ score: 25, severity: 'critical' })
    expect(previewAssessment(2, 2)).toEqual({ score: 4, severity: 'low' })
    expect(previewAssessment(0, 3).score).toBeNull()
  })

  it('requires title on create form', () => {
    expect(validateRiskForm(emptyRiskForm())).toEqual({ title: 'required' })
    expect(validateRiskForm({ ...emptyRiskForm(), title: 'خطر' })).toEqual({})
  })

  it('requires treatment core fields', () => {
    expect(
      validateTreatmentForm({
        title: '',
        plan_body: '',
        owner_employee_id: '',
        due_at: '',
        status: 'planned',
      }),
    ).toEqual({
      title: 'required',
      plan_body: 'required',
      owner_employee_id: 'required',
    })
  })

  it('surfaces need_season before loading', () => {
    expect(
      resolveRisksListState({ hasSeason: false, isLoading: true, isError: false, count: 0 }),
    ).toBe('need_season')
    expect(
      resolveRisksListState({ hasSeason: true, isLoading: false, isError: false, count: 0 }),
    ).toBe('empty')
  })

  it('hides approve for creator even with permission (SoD hint)', () => {
    expect(
      canApproveRiskAssessment({
        risk: { status: 'under_assessment', created_by: { id: 7, name: 'a', email: 'a@x' } },
        currentUserId: 7,
        hasApprovePermission: true,
      }),
    ).toBe(false)
    expect(
      canApproveRiskAssessment({
        risk: { status: 'under_assessment', created_by: { id: 7, name: 'a', email: 'a@x' } },
        currentUserId: 9,
        hasApprovePermission: true,
      }),
    ).toBe(true)
    expect(
      canApproveRiskAssessment({
        risk: { status: 'under_assessment', created_by: { id: 7, name: 'a', email: 'a@x' } },
        currentUserId: 9,
        hasApprovePermission: false,
      }),
    ).toBe(false)
  })
})
