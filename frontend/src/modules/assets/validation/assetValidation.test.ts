import { describe, expect, it } from 'vitest'

import type {
  AssetFormState,
  AssignCustodyFormState,
  LifecycleReasonFormState,
  ReturnCustodyFormState,
} from '../types/assets'
import {
  assetStatusDotClass,
  assetsSidebarItems,
  availableAssetLifecycleActions,
  canShowAssignAction,
  canShowReturnAction,
  emptyAssetForm,
  emptyAssignForm,
  emptyReturnForm,
  filterAssetsListParams,
  validateAssetForm,
  validateAssignForm,
  validateLifecycleReasonForm,
  validateReturnForm,
} from './assetValidation'

function assetForm(overrides: Partial<AssetFormState> = {}): AssetFormState {
  return { ...emptyAssetForm(), ...overrides }
}

function assignForm(overrides: Partial<AssignCustodyFormState> = {}): AssignCustodyFormState {
  return { ...emptyAssignForm(), ...overrides }
}

function returnForm(overrides: Partial<ReturnCustodyFormState> = {}): ReturnCustodyFormState {
  return { ...emptyReturnForm(), ...overrides }
}

function reasonForm(overrides: Partial<LifecycleReasonFormState> = {}): LifecycleReasonFormState {
  return { reason: '', ...overrides }
}

describe('validateAssetForm', () => {
  it('requires a non-empty name', () => {
    expect(validateAssetForm(assetForm())).toEqual({ name: 'required' })
    expect(validateAssetForm(assetForm({ name: '   ' }))).toEqual({ name: 'required' })
    expect(validateAssetForm(assetForm({ name: 'جهاز محمول' }))).toEqual({})
  })

  it('rejects invalid purchase_value when provided', () => {
    expect(validateAssetForm(assetForm({ name: 'أصل', purchase_value: '-1' }))).toEqual({
      purchase_value: 'invalid',
    })
    expect(validateAssetForm(assetForm({ name: 'أصل', purchase_value: 'abc' }))).toEqual({
      purchase_value: 'invalid',
    })
    expect(validateAssetForm(assetForm({ name: 'أصل', purchase_value: '10.5' }))).toEqual({})
  })
})

describe('assetStatusDotClass', () => {
  it('returns a distinct visual dot for each lifecycle status', () => {
    expect(assetStatusDotClass('available')).toBe('bg-emerald-500')
    expect(assetStatusDotClass('in_use')).toBe('bg-sky-500')
    expect(assetStatusDotClass('maintenance')).toBe('bg-amber-500')
    expect(assetStatusDotClass('damaged')).toBe('bg-orange-500')
    expect(assetStatusDotClass('retired')).toBe('bg-slate-500')
    expect(assetStatusDotClass('lost')).toBe('bg-red-500')
  })
})

describe('validateAssignForm', () => {
  it('requires employee_id', () => {
    expect(validateAssignForm(assignForm())).toEqual({ employee_id: 'required' })
    expect(validateAssignForm(assignForm({ employee_id: 5 }))).toEqual({})
  })
})

describe('validateReturnForm', () => {
  it('requires next_status', () => {
    expect(validateReturnForm(returnForm({ next_status: '' }))).toEqual({ next_status: 'required' })
    expect(validateReturnForm(returnForm({ next_status: 'available' }))).toEqual({})
  })
})

describe('validateLifecycleReasonForm', () => {
  it('requires a non-empty reason', () => {
    expect(validateLifecycleReasonForm(reasonForm())).toEqual({ reason: 'required' })
    expect(validateLifecycleReasonForm(reasonForm({ reason: '  ' }))).toEqual({ reason: 'required' })
    expect(validateLifecycleReasonForm(reasonForm({ reason: 'تالف' }))).toEqual({})
  })
})

describe('availableAssetLifecycleActions', () => {
  it('offers assign/maintenance/retire/lost for available assets', () => {
    const actions = availableAssetLifecycleActions('available').map((a) => a.action)
    expect(actions).toEqual(['assign', 'maintenance', 'retire', 'declare_lost'])
  })

  it('offers return (and lost) for in_use assets', () => {
    const actions = availableAssetLifecycleActions('in_use').map((a) => a.action)
    expect(actions).toContain('return')
    expect(actions).toContain('declare_lost')
    expect(actions).not.toContain('assign')
  })

  it('offers restore/retire/lost for maintenance and damaged', () => {
    expect(availableAssetLifecycleActions('maintenance').map((a) => a.action)).toEqual([
      'restore',
      'retire',
      'declare_lost',
    ])
    expect(availableAssetLifecycleActions('damaged').map((a) => a.action)).toEqual([
      'restore',
      'retire',
      'declare_lost',
    ])
  })

  it('shows return when hasActiveCustody even if status is not in_use', () => {
    const actions = availableAssetLifecycleActions('available', { hasActiveCustody: true }).map(
      (a) => a.action,
    )
    expect(actions).toContain('return')
  })
})

describe('canShowAssignAction / canShowReturnAction', () => {
  it('gates assign on available + assets.assign', () => {
    expect(canShowAssignAction('available', ['assets.assign'])).toBe(true)
    expect(canShowAssignAction('in_use', ['assets.assign'])).toBe(false)
    expect(canShowAssignAction('available', ['assets.view'])).toBe(false)
  })

  it('gates return on active custody + assets.return', () => {
    expect(canShowReturnAction('in_use', ['assets.return'])).toBe(true)
    expect(canShowReturnAction('available', ['assets.return'], true)).toBe(true)
    expect(canShowReturnAction('available', ['assets.return'], false)).toBe(false)
    expect(canShowReturnAction('in_use', ['assets.view'])).toBe(false)
  })
})

describe('filterAssetsListParams', () => {
  it('omits empty filters and keeps pagination', () => {
    expect(
      filterAssetsListParams({
        search: '  ',
        status: '',
        category_id: '',
        warehouse_id: '',
        organization_unit_id: '',
        employee_id: '',
        page: 2,
        per_page: 15,
      }),
    ).toEqual({ page: 2, per_page: 15 })
  })

  it('includes provided filters', () => {
    expect(
      filterAssetsListParams({
        search: ' لابتوب ',
        status: 'available',
        category_id: 3,
        warehouse_id: 4,
        organization_unit_id: 5,
        employee_id: 6,
        page: 1,
        per_page: 20,
      }),
    ).toEqual({
      page: 1,
      per_page: 20,
      search: 'لابتوب',
      status: 'available',
      category_id: 3,
      warehouse_id: 4,
      organization_unit_id: 5,
      employee_id: 6,
    })
  })
})

describe('assetsSidebarItems', () => {
  it('returns assets and my-custodies when assets.view is granted', () => {
    expect(assetsSidebarItems((p) => p === 'assets.view')).toEqual([
      { key: 'assets', to: '/app/assets', labelKey: 'nav.assets' },
      { key: 'my-custodies', to: '/app/my-custodies', labelKey: 'nav.myCustodies' },
    ])
  })

  it('returns empty without assets.view', () => {
    expect(assetsSidebarItems(() => false)).toEqual([])
  })
})
