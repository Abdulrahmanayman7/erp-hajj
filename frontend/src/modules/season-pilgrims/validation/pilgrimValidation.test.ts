import { describe, expect, it } from 'vitest'

import {
  canRevealIdentityDocumentNumbers,
  canShowCreatePilgrimCta,
  canShowEditPilgrimCta,
  resolvePilgrimsListState,
  validatePilgrimForm,
} from './pilgrimValidation'

describe('pilgrims list view states', () => {
  it('resolves loading / empty / error / ready', () => {
    expect(resolvePilgrimsListState({ isLoading: true, isError: false, count: 0 })).toBe('loading')
    expect(resolvePilgrimsListState({ isLoading: false, isError: true, count: 0 })).toBe('error')
    expect(resolvePilgrimsListState({ isLoading: false, isError: false, count: 0 })).toBe('empty')
    expect(resolvePilgrimsListState({ isLoading: false, isError: false, count: 2 })).toBe('ready')
  })
})

describe('pilgrim form validation', () => {
  it('requires full_name and validates nationality format', () => {
    expect(
      validatePilgrimForm({
        full_name: '',
        gender: '',
        nationality: 'SAUDI',
        phone: '',
        notes: '',
      }),
    ).toEqual({ full_name: 'required', nationality: 'format' })

    expect(
      validatePilgrimForm({
        full_name: 'أحمد',
        gender: 'male',
        nationality: 'SA',
        phone: '',
        notes: '',
      }),
    ).toEqual({})
  })
})

describe('permission-aware pilgrim CTAs', () => {
  it('gates create/edit and document number reveal', () => {
    expect(canShowCreatePilgrimCta(['pilgrims.view'])).toBe(false)
    expect(canShowCreatePilgrimCta(['pilgrims.create'])).toBe(true)
    expect(canShowEditPilgrimCta(['pilgrims.update'])).toBe(true)
    expect(canRevealIdentityDocumentNumbers(['pilgrims.view'])).toBe(false)
    expect(canRevealIdentityDocumentNumbers(['pilgrims.manage_identity_documents'])).toBe(true)
  })
})
