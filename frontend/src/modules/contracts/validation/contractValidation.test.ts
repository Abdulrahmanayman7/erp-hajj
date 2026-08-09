import { describe, expect, it } from 'vitest'

import { ar } from '@/locales/ar'

import type { ContractFormState } from '../types/contracts'
import {
  availableLifecycleActions,
  canShowCategoriesButton,
  canShowCreateContractCta,
  mapContractErrorCode,
  resolveContractsListState,
  validateCategoryForm,
  validateContractForm,
} from './contractValidation'

function form(partial: Partial<ContractFormState> = {}): ContractFormState {
  return {
    title: '',
    contract_category_id: '',
    counterparty_name: '',
    counterparty_kind: 'organization',
    employee_id: '',
    organization_unit_id: '',
    start_date: '',
    end_date: '',
    value: '',
    currency: 'SAR',
    notes: '',
    ...partial,
  }
}

describe('contracts list view states', () => {
  it('resolves loading / empty / error / ready', () => {
    expect(resolveContractsListState({ isLoading: true, isError: false, count: 0 })).toBe(
      'loading',
    )
    expect(resolveContractsListState({ isLoading: false, isError: true, count: 0 })).toBe('error')
    expect(resolveContractsListState({ isLoading: false, isError: false, count: 0 })).toBe('empty')
    expect(resolveContractsListState({ isLoading: false, isError: false, count: 3 })).toBe('ready')
  })
})

describe('create / edit drawer validation', () => {
  it('requires title, category, counterparty and start_date', () => {
    expect(validateContractForm(form())).toEqual({
      title: 'required',
      contract_category_id: 'required',
      counterparty_name: 'required',
      start_date: 'required',
    })
  })

  it('rejects end_date before start_date', () => {
    expect(
      validateContractForm(
        form({
          title: 'عقد',
          contract_category_id: 1,
          counterparty_name: 'طرف',
          start_date: '2026-06-01',
          end_date: '2026-01-01',
        }),
      ),
    ).toEqual({ end_date: 'dateRange' })
  })

  it('rejects non-numeric value', () => {
    expect(
      validateContractForm(
        form({
          title: 'عقد',
          contract_category_id: 1,
          counterparty_name: 'طرف',
          start_date: '2026-01-01',
          value: 'abc',
        }),
      ),
    ).toEqual({ value: 'numeric' })
  })
})

describe('permission-aware CTAs', () => {
  it('shows create CTA only with contracts.create', () => {
    expect(canShowCreateContractCta(['contracts.view'])).toBe(false)
    expect(canShowCreateContractCta(['contracts.view', 'contracts.create'])).toBe(true)
  })

  it('shows categories button with view or update', () => {
    expect(canShowCategoriesButton(['contracts.view'])).toBe(true)
    expect(canShowCategoriesButton(['contracts.update'])).toBe(true)
    expect(canShowCategoriesButton([])).toBe(false)
  })
})

describe('lifecycle actions by status', () => {
  it('offers submit and cancel for draft', () => {
    expect(availableLifecycleActions('draft').map((a) => a.action)).toEqual([
      'submit_review',
      'cancel',
    ])
  })

  it('hides renew when already renewed', () => {
    expect(
      availableLifecycleActions('executing', { hasRenewalChild: true }).map((a) => a.action),
    ).toEqual(['close'])
    expect(
      availableLifecycleActions('executing', { hasRenewalChild: false }).map((a) => a.action),
    ).toEqual(['close', 'renew'])
  })

  it('requires comment for return_draft and cancel', () => {
    const review = availableLifecycleActions('in_review')
    expect(review.find((a) => a.action === 'return_draft')?.requiresComment).toBe(true)
    expect(review.find((a) => a.action === 'cancel')?.requiresComment).toBe(true)
  })
})

describe('renewal error mapping', () => {
  it('maps CONTRACT_ALREADY_RENEWED to Arabic copy', () => {
    expect(mapContractErrorCode('CONTRACT_ALREADY_RENEWED')).toBe('CONTRACT_ALREADY_RENEWED')
    expect(ar.contracts.errors.CONTRACT_ALREADY_RENEWED).toMatch(/تجديد|مسبقاً/)
  })
})

describe('sign confirmation copy', () => {
  it('states manual attestation not electronic signature', () => {
    expect(ar.contracts.confirmSignBody).toContain('توثيق يدوي')
    expect(ar.contracts.confirmSignBody).toContain('ليس توقيعاً إلكترونياً')
  })
})

describe('categories management basics', () => {
  it('requires category name', () => {
    expect(validateCategoryForm({ name: '', code: '' })).toEqual({ name: 'required' })
    expect(validateCategoryForm({ name: 'توريد', code: 'SUP' })).toEqual({})
  })

  it('maps CONTRACT_CATEGORY_IN_USE for delete blocked', () => {
    expect(mapContractErrorCode('CONTRACT_CATEGORY_IN_USE')).toBe('CONTRACT_CATEGORY_IN_USE')
    expect(ar.contracts.errors.CONTRACT_CATEGORY_IN_USE).toMatch(/عطّل|تعطيل/)
  })
})

describe('list column and status labels', () => {
  it('exposes required table column labels', () => {
    expect(ar.contracts.columns.contractNumber).toBe('رقم العقد')
    expect(ar.contracts.columns.title).toBe('العنوان')
    expect(ar.contracts.columns.category).toBe('التصنيف')
    expect(ar.contracts.columns.counterparty).toBe('الطرف الآخر')
    expect(ar.contracts.columns.startDate).toBe('البداية')
    expect(ar.contracts.columns.endDate).toBe('النهاية')
    expect(ar.contracts.columns.status).toBe('الحالة')
    expect(ar.contracts.columns.value).toBe('القيمة')
    expect(ar.contracts.columns.actions).toBe('إجراءات')
  })

  it('exposes Arabic status labels from UI spec', () => {
    expect(ar.contracts.status.draft).toBe('مسودة')
    expect(ar.contracts.status.in_review).toBe('قيد المراجعة')
    expect(ar.contracts.status.approved).toBe('معتمد')
    expect(ar.contracts.status.signed).toBe('موقّع')
    expect(ar.contracts.status.executing).toBe('قيد التنفيذ')
    expect(ar.contracts.status.closed).toBe('مغلق')
    expect(ar.contracts.status.renewed).toBe('مجدّد')
    expect(ar.contracts.status.expired).toBe('منتهي')
    expect(ar.contracts.status.cancelled).toBe('ملغى')
  })

  it('exposes page header and CTA labels from UI spec', () => {
    expect(ar.contracts.title).toBe('العقود')
    expect(ar.contracts.subtitle).toBe('إدارة العقود ودورة اعتمادها وتنفيذها')
    expect(ar.contracts.add).toBe('إضافة عقد')
    expect(ar.contracts.categoriesLink).toBe('تصنيفات العقود')
    expect(ar.contracts.expiringSoonBadge).toBe('ينتهي قريبًا')
    expect(ar.contracts.systemActor).toBe('النظام')
    expect(ar.contracts.attachmentsPlaceholder).toBe('المرفقات ستتوفر مع وحدة المستندات')
    expect(ar.nav.contracts).toBe('العقود')
  })
})
