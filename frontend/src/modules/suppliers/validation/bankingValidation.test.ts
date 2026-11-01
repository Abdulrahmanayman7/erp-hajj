import { describe, expect, it } from 'vitest'

import type { SupplierBankAccount } from '../types/banking'
import {
  assertMaskedBankAccount,
  bankingActionVisibility,
  containsForbiddenBankSensitiveKeys,
  emptyBankAccountForm,
  formForSupersede,
  formFromMaskedAccount,
  mapBankingErrorCode,
  resolveBankingListState,
  scrubRevealPayload,
  validateBankAccountForm,
  validateReasonMin10,
} from './bankingValidation'
import { mapSupplierErrorCode } from './supplierValidation'
import { ar } from '@/locales/ar'

function masked(overrides: Partial<SupplierBankAccount> = {}): SupplierBankAccount {
  return {
    id: 1,
    supplier_id: 9,
    account_key: 'ak',
    version_no: 1,
    label: null,
    account_holder_name: 'شركة',
    bank_name: 'بنك',
    iban_masked: '****7519',
    account_masked: null,
    currency: 'SAR',
    status: 'pending',
    is_primary: false,
    is_current: true,
    supersedes_id: null,
    evidence_document_id: null,
    created_by: 1,
    verified_by: null,
    verified_at: null,
    ...overrides,
  }
}

describe('bankingValidation', () => {
  it('requires holder, bank, and IBAN or local account; SAR only', () => {
    expect(validateBankAccountForm(emptyBankAccountForm())).toEqual({
      account_holder_name: 'holderRequired',
      bank_name: 'bankRequired',
      identifier: 'identifierRequired',
    })

    const form = emptyBankAccountForm()
    form.account_holder_name = 'شركة'
    form.bank_name = 'بنك'
    form.iban = 'SA0380000000608010167519'
    form.currency = 'USD'
    expect(validateBankAccountForm(form).currency).toBe('currencySarOnly')

    form.currency = 'SAR'
    expect(validateBankAccountForm(form)).toEqual({})
  })

  it('requires reveal/reject reason of at least 10 trimmed characters', () => {
    expect(validateReasonMin10('قصير').reason).toBe('reasonMin10')
    expect(validateReasonMin10('   قصير جدا   ').reason).toBe('reasonMin10')
    expect(validateReasonMin10('مراجعة تشغيلية مشروعة')).toEqual({})
  })

  it('does not copy full identifiers into edit or supersede forms', () => {
    const account = masked({
      account_holder_name: 'شركة الاختبار',
      bank_name: 'مصرف',
      label: 'رئيسي',
      evidence_document_id: 44,
    })
    const edit = formFromMaskedAccount(account)
    expect(edit.iban).toBe('')
    expect(edit.account_number).toBe('')
    expect(edit.account_holder_name).toBe('شركة الاختبار')

    const supersede = formForSupersede(account)
    expect(supersede.iban).toBe('')
    expect(supersede.account_number).toBe('')
    expect(supersede.evidence_document_id).toBe('')
    expect(supersede.currency).toBe('SAR')
  })

  it('maps manage/verify/reveal affordances correctly', () => {
    const pending = masked({ status: 'pending' })
    const manageOnly = bankingActionVisibility(pending, {
      canManage: true,
      canVerify: false,
      canReveal: false,
    })
    expect(manageOnly.edit).toBe(true)
    expect(manageOnly.verify).toBe(false)
    expect(manageOnly.reject).toBe(false)
    expect(manageOnly.reveal).toBe(false)

    const verifyOnly = bankingActionVisibility(pending, {
      canManage: false,
      canVerify: true,
      canReveal: false,
    })
    expect(verifyOnly.verify).toBe(true)
    expect(verifyOnly.reject).toBe(true)
    expect(verifyOnly.edit).toBe(false)
    expect(verifyOnly.reveal).toBe(false)

    const verified = masked({ status: 'verified', is_primary: true })
    const revealOnly = bankingActionVisibility(verified, {
      canManage: false,
      canVerify: false,
      canReveal: true,
    })
    expect(revealOnly.reveal).toBe(true)
    expect(revealOnly.setPrimary).toBe(false)
    expect(revealOnly.clearPrimary).toBe(false)
    expect(revealOnly.revoke).toBe(false)
  })

  it('preserves historical primary affordance rules', () => {
    const historicalPrimary = masked({
      status: 'verified',
      is_primary: true,
      is_current: false,
      version_no: 1,
    })
    const actions = bankingActionVisibility(historicalPrimary, {
      canManage: true,
      canVerify: true,
      canReveal: true,
    })
    expect(actions.clearPrimary).toBe(true)
    expect(actions.supersede).toBe(false)
    expect(actions.setPrimary).toBe(false)
  })

  it('detects forbidden sensitive keys and asserts masked shape', () => {
    expect(assertMaskedBankAccount(masked())).toBe(true)
    expect(containsForbiddenBankSensitiveKeys({ iban_masked: '****7519' })).toBe(false)
    expect(containsForbiddenBankSensitiveKeys({ iban: 'SA038…' })).toBe(true)
    expect(containsForbiddenBankSensitiveKeys({ nested: { iban_fingerprint: 'x' } })).toBe(true)
  })

  it('scrubs reveal payload plaintext', () => {
    const payload = {
      id: 1,
      supplier_id: 9,
      account_key: 'ak',
      version_no: 1,
      status: 'verified',
      iban: 'SA0380000000608010167519',
      account_number: '123',
      iban_last4: '7519',
      account_last4: '123',
    }
    expect(scrubRevealPayload(payload)).toBeNull()
    expect(payload.iban).toBeNull()
    expect(payload.account_number).toBeNull()
  })

  it('resolves list states including forbidden', () => {
    expect(resolveBankingListState({ canView: false, isLoading: true, isError: false, count: 0 })).toBe(
      'forbidden',
    )
    expect(resolveBankingListState({ canView: true, isLoading: true, isError: false, count: 0 })).toBe(
      'loading',
    )
    expect(resolveBankingListState({ canView: true, isLoading: false, isError: true, count: 0 })).toBe(
      'error',
    )
    expect(resolveBankingListState({ canView: true, isLoading: false, isError: false, count: 0 })).toBe(
      'empty',
    )
    expect(resolveBankingListState({ canView: true, isLoading: false, isError: false, count: 2 })).toBe(
      'ready',
    )
  })

  it('maps banking domain codes through supplier error mapper and i18n', () => {
    expect(mapBankingErrorCode('SUPPLIER_BANK_IBAN_DUPLICATE')).toBe('SUPPLIER_BANK_IBAN_DUPLICATE')
    expect(mapSupplierErrorCode('SUPPLIER_BANK_SOD_VIOLATION')).toBe('SUPPLIER_BANK_SOD_VIOLATION')
    expect(mapSupplierErrorCode('SUPPLIER_BANK_REVEAL_REASON_REQUIRED')).toBe(
      'SUPPLIER_BANK_REVEAL_REASON_REQUIRED',
    )
    expect(ar.suppliers.errors.SUPPLIER_BANK_IBAN_DUPLICATE).toBeTruthy()
    expect(ar.suppliers.banking.revealTitle).toBeTruthy()
    expect(ar.suppliers.phaseIILockedBody).not.toContain('البيانات البنكية')
  })
})
