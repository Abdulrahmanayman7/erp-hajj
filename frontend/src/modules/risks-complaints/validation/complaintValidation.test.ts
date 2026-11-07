import { describe, expect, it } from 'vitest'

import {
  canCloseComplaint,
  canReopenComplaint,
  emptyComplaintForm,
  resolveComplaintsListState,
  validateComplaintForm,
} from './complaintValidation'

describe('complaintValidation', () => {
  it('requires intake fields', () => {
    expect(validateComplaintForm(emptyComplaintForm())).toEqual({
      title: 'required',
      body: 'required',
      category_code: 'required',
    })
    expect(
      validateComplaintForm({
        ...emptyComplaintForm(),
        title: 'شكوى',
        body: 'نص',
        category_code: 'service',
      }),
    ).toEqual({})
  })

  it('gates close/reopen by status only (permission checked separately)', () => {
    expect(canCloseComplaint('resolved')).toBe(true)
    expect(canCloseComplaint('in_progress')).toBe(false)
    expect(canReopenComplaint('closed')).toBe(true)
    expect(canReopenComplaint('resolved')).toBe(false)
  })

  it('surfaces need_season before loading', () => {
    expect(
      resolveComplaintsListState({ hasSeason: false, isLoading: true, isError: false, count: 0 }),
    ).toBe('need_season')
    expect(
      resolveComplaintsListState({ hasSeason: true, isLoading: false, isError: false, count: 2 }),
    ).toBe('ready')
  })
})
