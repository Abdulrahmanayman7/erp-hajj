import { describe, expect, it } from 'vitest'

import type { Season } from '../types/seasons'
import {
  canActivateSeason,
  canCloseSeason,
  canEditSeason,
  canReopenSeason,
  canShowCreateSeasonCta,
  resolveSeasonsListState,
  validateSeasonForm,
} from './seasonValidation'

function season(partial: Partial<Season> = {}): Season {
  return {
    id: 1,
    code: 'H1447',
    name: 'موسم',
    hajj_year: 1447,
    status: 'draft',
    operational_starts_on: '2025-05-01',
    operational_ends_on: '2025-07-01',
    notes: null,
    first_activated_at: null,
    activated_at: null,
    closed_at: null,
    reopened_at: null,
    created_at: null,
    updated_at: null,
    ...partial,
  }
}

describe('seasons list view states', () => {
  it('resolves loading / empty / error / ready', () => {
    expect(resolveSeasonsListState({ isLoading: true, isError: false, count: 0 })).toBe('loading')
    expect(resolveSeasonsListState({ isLoading: false, isError: true, count: 0 })).toBe('error')
    expect(resolveSeasonsListState({ isLoading: false, isError: false, count: 0 })).toBe('empty')
    expect(resolveSeasonsListState({ isLoading: false, isError: false, count: 1 })).toBe('ready')
  })
})

describe('season form validation and lifecycle gates', () => {
  it('validates required fields and date window', () => {
    expect(
      validateSeasonForm({
        code: '',
        name: '',
        hajj_year: '99',
        operational_starts_on: '2025-07-01',
        operational_ends_on: '2025-05-01',
        notes: '',
      }),
    ).toMatchObject({
      code: 'required',
      name: 'required',
      hajj_year: 'range',
      operational_ends_on: 'after',
    })
  })

  it('blocks edit on closed seasons and maps lifecycle CTAs', () => {
    expect(canEditSeason(season({ status: 'closed' }))).toBe(false)
    expect(canEditSeason(season({ status: 'active' }))).toBe(true)
    expect(canActivateSeason(season({ status: 'draft' }))).toBe(true)
    expect(canCloseSeason(season({ status: 'active' }))).toBe(true)
    expect(canReopenSeason(season({ status: 'closed' }))).toBe(true)
    expect(canShowCreateSeasonCta(['seasons.view'])).toBe(false)
    expect(canShowCreateSeasonCta(['seasons.create'])).toBe(true)
  })
})
