import { describe, expect, it } from 'vitest'

import type { SupplierFormState } from '../types/suppliers'
import {
  availableSupplierLifecycleActions,
  mapSupplierErrorCode,
  resolveSuppliersListState,
  supplierStatusBadgeClass,
  validateCategoryForm,
  validateSupplierForm,
} from './supplierValidation'

function supplierForm(partial: Partial<SupplierFormState> = {}): SupplierFormState {
  return {
    display_name: '',
    legal_name: '',
    supplier_type: 'company',
    country_code: 'SA',
    legal_id_type: '',
    legal_id_value: '',
    notes: '',
    category_ids: [],
    ...partial,
  }
}

describe('suppliers list view states', () => {
  it('resolves loading / empty / error / ready', () => {
    expect(resolveSuppliersListState({ isLoading: true, isError: false, count: 0 })).toBe(
      'loading',
    )
    expect(resolveSuppliersListState({ isLoading: false, isError: true, count: 0 })).toBe('error')
    expect(resolveSuppliersListState({ isLoading: false, isError: false, count: 0 })).toBe('empty')
    expect(resolveSuppliersListState({ isLoading: false, isError: false, count: 2 })).toBe('ready')
  })
})

describe('supplier form validation', () => {
  it('requires display name, type and valid country code', () => {
    expect(validateSupplierForm(supplierForm({ display_name: '', country_code: '' }))).toEqual({
      display_name: 'required',
      country_code: 'countryCode',
    })
  })

  it('accepts valid minimal form', () => {
    expect(
      validateSupplierForm(
        supplierForm({ display_name: 'مورد', supplier_type: 'company', country_code: 'SA' }),
      ),
    ).toEqual({})
  })
})

describe('category form validation', () => {
  it('requires name', () => {
    expect(validateCategoryForm({ name: '', code: '' })).toEqual({ name: 'required' })
  })
})

describe('lifecycle actions', () => {
  it('offers activate from under_review (backend still blocks with SUPPLIER_NOT_ELIGIBLE)', () => {
    const actions = availableSupplierLifecycleActions('under_review')
    expect(actions.some((a) => a.to === 'activate')).toBe(true)
    expect(actions.every((a) => a.permission === 'suppliers.transition')).toBe(true)
  })

  it('does not offer actions for archived', () => {
    expect(availableSupplierLifecycleActions('archived')).toEqual([])
  })

  it('does not expose banking or evaluation lifecycle verbs', () => {
    const draft = availableSupplierLifecycleActions('draft')
    expect(draft.every((a) => !String(a.to).includes('bank'))).toBe(true)
  })
})

describe('error code mapping', () => {
  it('maps SUPPLIER_NOT_ELIGIBLE', () => {
    expect(mapSupplierErrorCode('SUPPLIER_NOT_ELIGIBLE')).toBe('SUPPLIER_NOT_ELIGIBLE')
  })

  it('falls back to generic', () => {
    expect(mapSupplierErrorCode('UNKNOWN')).toBe('generic')
  })
})

describe('status badge classes', () => {
  it('returns classes for known statuses', () => {
    expect(supplierStatusBadgeClass('draft')).toContain('slate')
    expect(supplierStatusBadgeClass('active')).toContain('emerald')
  })
})
