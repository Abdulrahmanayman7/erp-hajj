import { describe, expect, it } from 'vitest'

import {
  mapEnrollmentErrorCode,
  resolveSeasonScopedListState,
  validateEnrollForm,
  validateFamilyForm,
  validatePilgrimGroupForm,
  validateProgramForm,
} from './enrollmentValidation'

describe('season enrollment validation', () => {
  it('requires pilgrim_id', () => {
    expect(
      validateEnrollForm({
        pilgrim_id: '',
        season_program_id: '',
        season_pilgrim_group_id: '',
        season_family_group_id: '',
      }),
    ).toEqual({ pilgrim_id: 'required' })

    expect(
      validateEnrollForm({
        pilgrim_id: 5,
        season_program_id: 1,
        season_pilgrim_group_id: '',
        season_family_group_id: '',
      }),
    ).toEqual({})
  })

  it('maps duplicate and closed-season API codes', () => {
    expect(mapEnrollmentErrorCode('SEASON_PILGRIM_ALREADY_REGISTERED')).toBe('alreadyRegistered')
    expect(mapEnrollmentErrorCode('SEASON_CLOSED')).toBe('seasonClosed')
    expect(mapEnrollmentErrorCode('UNKNOWN')).toBe('generic')
  })
})

describe('season-scoped entity form validation', () => {
  it('validates group and program code/name', () => {
    expect(validatePilgrimGroupForm({ code: '', name: '', notes: '' })).toEqual({
      code: 'required',
      name: 'required',
    })
    expect(validateProgramForm({ code: 'P1', name: 'برنامج', description: '' })).toEqual({})
    expect(validateFamilyForm({ name: '', notes: '' })).toEqual({ name: 'required' })
  })

  it('resolves need_season / empty / ready states', () => {
    expect(
      resolveSeasonScopedListState({
        hasSeason: false,
        isLoading: false,
        isError: false,
        count: 0,
      }),
    ).toBe('need_season')
    expect(
      resolveSeasonScopedListState({
        hasSeason: true,
        isLoading: false,
        isError: false,
        count: 0,
      }),
    ).toBe('empty')
    expect(
      resolveSeasonScopedListState({
        hasSeason: true,
        isLoading: false,
        isError: false,
        count: 2,
      }),
    ).toBe('ready')
  })
})
