import { describe, expect, it } from 'vitest'
import {
  availableLifecycleActions,
  canCreateDecisionFromRecommendation,
  resolveDecisionsListState,
  validateDecisionForm,
} from './decisionValidation'

describe('decision UI rules', () => {
  it('resolves loading and empty list states', () => {
    expect(resolveDecisionsListState({ isLoading: true, isError: false, count: 0 })).toBe('loading')
    expect(resolveDecisionsListState({ isLoading: false, isError: false, count: 0 })).toBe('empty')
  })
  it('requires decision data when creating', () => {
    expect(validateDecisionForm({ title: '', body: '', notes: '', organization_unit_id: '', issued_by_employee_id: '', responsible_employee_id: '', effective_date: '', due_date: '' })).toMatchObject({ title: 'required', body: 'required' })
  })
  it('offers lifecycle actions only for the matching status', () => {
    expect(availableLifecycleActions('draft').map(x => x.permission)).toEqual(['decisions.update', 'decisions.update'])
    expect(availableLifecycleActions('approved')).toEqual([{ action: 'close', permission: 'decisions.close' }])
  })
  it('shows the meeting CTA only for eligible recommendations', () => {
    expect(canCreateDecisionFromRecommendation({ recommendationStatus: 'final', meetingStatus: 'completed', hasLinkedDecision: false, permissions: ['decisions.create'] })).toBe(true)
    expect(canCreateDecisionFromRecommendation({ recommendationStatus: 'final', meetingStatus: 'completed', hasLinkedDecision: true, permissions: ['decisions.create'] })).toBe(false)
  })
})
