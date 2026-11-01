import { describe, expect, it } from 'vitest'

import { ar } from '@/locales/ar'

import type {
  EligibilityRequirementDef,
  SupplierDocumentRecord,
} from '../types/eligibility'
import type { Supplier } from '../types/suppliers'
import {
  applicableDefsForSupplier,
  buildChecklist,
  buildDefPayloads,
  canEditEligibilitySet,
  canSupersedeRecord,
  canVerifyOrRejectRecord,
  configLevelReasons,
  eligibilityReasonKey,
  emptyDefRow,
  emptySetForm,
  hasEligibilitySetErrors,
  isValidCalendarDate,
  mapEligibilityErrorCode,
  normalizeEligibilityReasons,
  validateDocumentRecordForm,
  validateEligibilitySetForm,
  verificationStatusBadgeClass,
} from './eligibilityValidation'
import { mapSupplierErrorCode } from './supplierValidation'

function def(partial: Partial<EligibilityRequirementDef> = {}): EligibilityRequirementDef {
  return {
    id: 100,
    requirement_key: 'commercial_registration',
    label_ar: 'السجل التجاري',
    supplier_type: 'company',
    country_code: null,
    supplier_category_id: null,
    is_mandatory: true,
    default_validity_days: 365,
    ...partial,
  }
}

function record(partial: Partial<SupplierDocumentRecord> = {}): SupplierDocumentRecord {
  return {
    id: 1,
    supplier_id: 9,
    requirement_def_id: 100,
    requirement_key: 'commercial_registration',
    document_id: 555,
    document_number: 'DOC-000123',
    verification_status: 'pending_verification',
    expires_at: null,
    uploaded_by: null,
    verified_by: null,
    verified_at: null,
    rejection_reason: null,
    supersedes_id: null,
    created_at: null,
    updated_at: null,
    ...partial,
  }
}

describe('eligibility reason / error mapping', () => {
  it('maps every stable reason code to an Arabic string in ar.ts', () => {
    const codes = [
      'SUPPLIER_DOCUMENT_MISSING',
      'SUPPLIER_DOCUMENT_EXPIRED',
      'SUPPLIER_DOCUMENT_REJECTED',
      'SUPPLIER_DOCUMENT_PENDING_VERIFICATION',
      'SUPPLIER_ELIGIBILITY_CONFIG_MISSING',
      'SUPPLIER_ELIGIBILITY_CONFIG_DRAFT',
      'SUPPLIER_DOCUMENT_EVIDENCE_INVALID',
      'SUPPLIER_ELIGIBILITY_NO_MANDATORY_REQUIREMENTS',
    ]
    codes.forEach((code) => {
      expect(eligibilityReasonKey(code)).toBe(code)
      const message = (ar.suppliers.eligibility.reasons as Record<string, string>)[code]
      expect(message).toMatch(/[\u0600-\u06ff]/)
    })
  })

  it('falls back to unknown for unrecognised reason codes', () => {
    expect(eligibilityReasonKey('NOPE')).toBe('unknown')
    expect(eligibilityReasonKey(undefined)).toBe('unknown')
  })

  it('maps II-A error codes through mapSupplierErrorCode and each has an ar.ts message', () => {
    const codes = [
      'SUPPLIER_NOT_ELIGIBLE',
      'SUPPLIER_ELIGIBILITY_CONFIG_IMMUTABLE',
      'SUPPLIER_ELIGIBILITY_CONFIG_NOT_DRAFT',
      'SUPPLIER_ELIGIBILITY_DEFS_EMPTY',
      'SUPPLIER_DOCUMENT_RECORD_EXISTS',
      'SUPPLIER_DOCUMENT_INVALID_STATE',
      'SUPPLIER_SOD_VIOLATION',
      'SUPPLIER_DOCUMENT_EXPIRED',
    ]
    codes.forEach((code) => {
      expect(mapSupplierErrorCode(code)).toBe(code)
      expect((ar.suppliers.errors as Record<string, string>)[code]).toMatch(/[\u0600-\u06ff]/)
    })
    expect(mapEligibilityErrorCode('SOMETHING_ELSE')).toBe('generic')
    expect(mapSupplierErrorCode('SOMETHING_ELSE')).toBe('generic')
  })

  it('normalises reasons from an error envelope', () => {
    expect(
      normalizeEligibilityReasons([
        { code: 'SUPPLIER_DOCUMENT_EXPIRED', requirement_key: 'zakat', expires_at: '2026-01-01' },
        { code: 'SUPPLIER_ELIGIBILITY_CONFIG_MISSING' },
        { nope: true },
        null,
      ]),
    ).toEqual([
      { code: 'SUPPLIER_DOCUMENT_EXPIRED', requirement_key: 'zakat', expires_at: '2026-01-01' },
      { code: 'SUPPLIER_ELIGIBILITY_CONFIG_MISSING', requirement_key: undefined, expires_at: undefined },
    ])
    expect(normalizeEligibilityReasons(undefined)).toEqual([])
  })

  it('separates config-level reasons', () => {
    const reasons = [
      { code: 'SUPPLIER_ELIGIBILITY_CONFIG_MISSING' },
      { code: 'SUPPLIER_DOCUMENT_MISSING', requirement_key: 'a' },
    ]
    expect(configLevelReasons(reasons)).toEqual([{ code: 'SUPPLIER_ELIGIBILITY_CONFIG_MISSING' }])
  })
})

describe('status helpers', () => {
  it('colours verification statuses', () => {
    expect(verificationStatusBadgeClass('valid')).toContain('emerald')
    expect(verificationStatusBadgeClass('rejected')).toContain('red')
    expect(verificationStatusBadgeClass('superseded')).toContain('slate')
  })

  it('offers record actions by state (server remains authoritative)', () => {
    expect(canVerifyOrRejectRecord(record())).toBe(true)
    expect(canVerifyOrRejectRecord(record({ verification_status: 'valid' }))).toBe(false)
    expect(canSupersedeRecord(record({ verification_status: 'valid' }))).toBe(true)
    expect(canSupersedeRecord(record({ verification_status: 'superseded' }))).toBe(false)
    expect(canEditEligibilitySet({ status: 'draft' })).toBe(true)
    expect(canEditEligibilitySet({ status: 'approved' })).toBe(false)
  })
})

describe('eligibility set form validation', () => {
  it('requires config key, name and at least one def on create', () => {
    const errors = validateEligibilitySetForm(
      { config_key: '', name: '', defs: [] },
      { isCreate: true },
    )
    expect(errors.config_key).toBe('required')
    expect(errors.name).toBe('required')
    expect(errors.defs).toBe('defsRequired')
    expect(hasEligibilitySetErrors(errors)).toBe(true)
  })

  it('does not validate config_key when editing', () => {
    const form = emptySetForm()
    form.name = 'x'
    form.defs = [{ ...emptyDefRow(), requirement_key: 'cr', label_ar: 'سجل' }]
    expect(hasEligibilitySetErrors(validateEligibilitySetForm(form, { isCreate: false }))).toBe(false)
  })

  it('validates key patterns, country code, validity days and duplicates', () => {
    const form = emptySetForm()
    form.config_key = 'Bad Key'
    form.name = 'x'
    form.defs = [
      { ...emptyDefRow(), requirement_key: 'Bad-Key', label_ar: '', country_code: 'SAU' },
      { ...emptyDefRow(), requirement_key: 'cr', label_ar: 'سجل', default_validity_days: 4000 },
      { ...emptyDefRow(), requirement_key: 'cr', label_ar: 'سجل', default_validity_days: 10 },
    ]
    const errors = validateEligibilitySetForm(form, { isCreate: true })
    expect(errors.config_key).toBe('configKey')
    expect(errors.defRows[0]).toEqual({
      requirement_key: 'requirementKey',
      label_ar: 'required',
      country_code: 'countryCode',
    })
    expect(errors.defRows[1]?.default_validity_days).toBe('validityDays')
    expect(errors.defRows[2]?.requirement_key).toBe('duplicateDef')
  })

  it('builds API payloads with nullable wildcards and never invents a goods type', () => {
    const payload = buildDefPayloads([
      {
        ...emptyDefRow(),
        requirement_key: ' cr ',
        label_ar: ' سجل ',
        country_code: 'sa',
        supplier_category_id: 3,
        default_validity_days: 365,
      },
      { ...emptyDefRow(), requirement_key: 'tax', label_ar: 'ضريبة', supplier_type: 'international', is_mandatory: false },
    ])
    expect(payload[0]).toEqual({
      requirement_key: 'cr',
      label_ar: 'سجل',
      supplier_type: 'company',
      country_code: 'SA',
      supplier_category_id: 3,
      is_mandatory: true,
      default_validity_days: 365,
    })
    expect(payload[1]).toMatchObject({
      country_code: null,
      supplier_category_id: null,
      default_validity_days: null,
      is_mandatory: false,
      supplier_type: 'international',
    })
    expect(payload.some((p) => (p.supplier_type as string) === 'goods')).toBe(false)
  })
})

describe('document record form validation', () => {
  it('requires a requirement def on create and a positive document id', () => {
    expect(
      validateDocumentRecordForm(
        { requirement_def_id: '', document_id: '', expires_at: '' },
        { requireDef: true },
      ),
    ).toEqual({ requirement_def_id: 'required', document_id: 'documentId' })
    expect(
      validateDocumentRecordForm(
        { requirement_def_id: '', document_id: 5, expires_at: '' },
        { requireDef: false },
      ),
    ).toEqual({})
  })

  it('validates expires_at as a real calendar date', () => {
    expect(isValidCalendarDate('2027-10-31')).toBe(true)
    expect(isValidCalendarDate('2027-02-30')).toBe(false)
    expect(isValidCalendarDate('31/10/2027')).toBe(false)
    expect(
      validateDocumentRecordForm(
        { requirement_def_id: 1, document_id: 5, expires_at: '2027-13-01' },
        { requireDef: true },
      ).expires_at,
    ).toBe('date')
  })
})

describe('checklist', () => {
  const supplier: Pick<Supplier, 'supplier_type' | 'country_code' | 'categories'> = {
    supplier_type: 'company',
    country_code: 'SA',
    categories: [{ id: 7, name: 'تموين', code: null, is_active: true }] as Supplier['categories'],
  }

  it('narrows defs by applicable keys, supplier type, country and category (UX only)', () => {
    const defs = [
      def({ id: 1, requirement_key: 'cr' }),
      def({ id: 2, requirement_key: 'cr', supplier_type: 'individual' }),
      def({ id: 3, requirement_key: 'tax', country_code: 'EG' }),
      def({ id: 4, requirement_key: 'cat', supplier_category_id: 99 }),
      def({ id: 5, requirement_key: 'cat2', supplier_category_id: 7, country_code: 'sa' }),
      def({ id: 6, requirement_key: 'not_applicable_key' }),
    ]
    const result = applicableDefsForSupplier(defs, supplier, ['cr', 'tax', 'cat', 'cat2'])
    expect(result.map((d) => d.id)).toEqual([1, 5])
  })

  it('pairs defs with the newest non-superseded record, history and server reasons', () => {
    const defs = [def({ id: 100, requirement_key: 'cr' }), def({ id: 101, requirement_key: 'zakat', label_ar: 'الزكاة' })]
    const records = [
      record({ id: 1, requirement_key: 'cr', verification_status: 'superseded' }),
      record({ id: 2, requirement_key: 'cr', verification_status: 'valid' }),
    ]
    const rows = buildChecklist(defs, records, {
      applicable_requirement_keys: ['cr', 'zakat'],
      reasons: [{ code: 'SUPPLIER_DOCUMENT_MISSING', requirement_key: 'zakat' }],
    })
    expect(rows.map((r) => r.requirementKey)).toEqual(['cr', 'zakat'])
    expect(rows[0]?.current?.id).toBe(2)
    expect(rows[0]?.history.map((r) => r.id)).toEqual([1])
    expect(rows[0]?.reasons).toEqual([])
    expect(rows[1]?.current).toBeNull()
    expect(rows[1]?.label).toBe('الزكاة')
    expect(rows[1]?.reasons[0]?.code).toBe('SUPPLIER_DOCUMENT_MISSING')
  })

  it('keeps rows for applicable keys even when the def cannot be loaded', () => {
    const rows = buildChecklist([], [], {
      applicable_requirement_keys: ['cr'],
      reasons: [],
    })
    expect(rows).toHaveLength(1)
    expect(rows[0]?.def).toBeNull()
    expect(rows[0]?.label).toBe('cr')
  })
})

describe('Phase II-A scope guard', () => {
  it('keeps banking and evaluations as phase II placeholders only (no UI strings added)', () => {
    expect(ar.suppliers.phaseIILockedBody).not.toContain('الوثائق')
    expect(ar.suppliers.tabs.banking).toBeTruthy()
    expect(ar.suppliers.tabs.evaluations).toBeTruthy()
  })
})
