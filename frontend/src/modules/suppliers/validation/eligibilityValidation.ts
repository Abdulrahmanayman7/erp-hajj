import type {
  DocumentVerificationStatus,
  EligibilityDefFormRow,
  EligibilityReason,
  EligibilityRequirementDef,
  EligibilityRequirementDefPayload,
  EligibilityRequirementSet,
  EligibilitySetFormState,
  SupplierDocumentRecord,
  SupplierEligibility,
} from '../types/eligibility'
import type { Supplier } from '../types/suppliers'

/** Stable II-A reason / error codes (API.md §12.5). */
export const ELIGIBILITY_REASON_CODES = [
  'SUPPLIER_DOCUMENT_MISSING',
  'SUPPLIER_DOCUMENT_EXPIRED',
  'SUPPLIER_DOCUMENT_REJECTED',
  'SUPPLIER_DOCUMENT_PENDING_VERIFICATION',
  'SUPPLIER_ELIGIBILITY_CONFIG_MISSING',
  'SUPPLIER_ELIGIBILITY_CONFIG_DRAFT',
  'SUPPLIER_DOCUMENT_EVIDENCE_INVALID',
  'SUPPLIER_ELIGIBILITY_NO_MANDATORY_REQUIREMENTS',
] as const

export const ELIGIBILITY_ERROR_CODES = [
  ...ELIGIBILITY_REASON_CODES,
  'SUPPLIER_ELIGIBILITY_CONFIG_IMMUTABLE',
  'SUPPLIER_ELIGIBILITY_CONFIG_NOT_DRAFT',
  'SUPPLIER_ELIGIBILITY_DEFS_EMPTY',
  'SUPPLIER_ELIGIBILITY_DEFS_NO_MANDATORY',
  'SUPPLIER_DOCUMENT_RECORD_EXISTS',
  'SUPPLIER_DOCUMENT_INVALID_STATE',
  'SUPPLIER_SOD_VIOLATION',
] as const

/** Returns the code itself when known, otherwise 'generic' (used as `suppliers.errors.<key>`). */
export function mapEligibilityErrorCode(code: string | undefined): string {
  if (code && (ELIGIBILITY_ERROR_CODES as readonly string[]).includes(code)) {
    return code
  }
  return 'generic'
}

/** i18n key suffix under `suppliers.eligibility.reasons.*`; unknown codes fall back to `unknown`. */
export function eligibilityReasonKey(code: string | undefined): string {
  if (code && (ELIGIBILITY_REASON_CODES as readonly string[]).includes(code)) {
    return code
  }
  return 'unknown'
}

/** Normalises the `reasons` array of an error envelope / eligibility response. */
export function normalizeEligibilityReasons(raw: unknown): EligibilityReason[] {
  if (!Array.isArray(raw)) return []
  const result: EligibilityReason[] = []
  raw.forEach((item) => {
    if (item && typeof item === 'object' && typeof (item as { code?: unknown }).code === 'string') {
      const value = item as Record<string, unknown>
      result.push({
        code: value.code as string,
        requirement_key:
          typeof value.requirement_key === 'string' ? value.requirement_key : undefined,
        expires_at: typeof value.expires_at === 'string' ? value.expires_at : undefined,
      })
    }
  })
  return result
}

export function verificationStatusBadgeClass(status: DocumentVerificationStatus): string {
  switch (status) {
    case 'valid':
      return 'bg-emerald-100 text-emerald-950 ring-1 ring-inset ring-emerald-300/80'
    case 'pending_verification':
      return 'bg-amber-100 text-amber-950 ring-1 ring-inset ring-amber-300/80'
    case 'rejected':
      return 'bg-red-100 text-red-950 ring-1 ring-inset ring-red-300/80'
    case 'expired':
      return 'bg-orange-100 text-orange-950 ring-1 ring-inset ring-orange-300/80'
    case 'superseded':
      return 'bg-slate-200/80 text-slate-800 ring-1 ring-inset ring-slate-400/50'
    default:
      return 'bg-neutral-100 text-neutral-700 ring-1 ring-inset ring-neutral-300/80'
  }
}

export function configStatusBadgeClass(status: 'draft' | 'approved'): string {
  return status === 'approved'
    ? 'bg-emerald-100 text-emerald-950 ring-1 ring-inset ring-emerald-300/80'
    : 'bg-amber-100 text-amber-950 ring-1 ring-inset ring-amber-300/80'
}

/** Record actions are UX-only; the server enforces state machine and SoD. */
export function canVerifyOrRejectRecord(record: Pick<SupplierDocumentRecord, 'verification_status'>): boolean {
  return record.verification_status === 'pending_verification'
}

export function canSupersedeRecord(record: Pick<SupplierDocumentRecord, 'verification_status'>): boolean {
  return ['pending_verification', 'valid', 'rejected', 'expired'].includes(
    record.verification_status,
  )
}

export function canEditEligibilitySet(set: Pick<EligibilityRequirementSet, 'status'>): boolean {
  return set.status === 'draft'
}

export function canCreateNewDraftFrom(set: Pick<EligibilityRequirementSet, 'status'>): boolean {
  return set.status === 'approved'
}

/* ------------------------------------------------------------------ */
/* Eligibility config form                                             */
/* ------------------------------------------------------------------ */

export interface EligibilityDefFieldErrors {
  requirement_key?: string
  label_ar?: string
  country_code?: string
  default_validity_days?: string
}

export interface EligibilitySetFieldErrors {
  config_key?: string
  name?: string
  defs?: string
  defRows: Record<number, EligibilityDefFieldErrors>
}

const CONFIG_KEY_PATTERN = /^[a-z0-9][a-z0-9_-]*$/
const REQUIREMENT_KEY_PATTERN = /^[a-z0-9][a-z0-9_]*$/

export function emptyDefRow(): EligibilityDefFormRow {
  return {
    requirement_key: '',
    label_ar: '',
    supplier_type: 'company',
    country_code: '',
    supplier_category_id: '',
    is_mandatory: true,
    default_validity_days: '',
  }
}

export function emptySetForm(): EligibilitySetFormState {
  return { config_key: '', name: '', defs: [emptyDefRow()] }
}

export function setToFormState(set: EligibilityRequirementSet): EligibilitySetFormState {
  return {
    config_key: set.config_key,
    name: set.name,
    defs: (set.requirement_defs ?? []).map((def) => ({
      requirement_key: def.requirement_key,
      label_ar: def.label_ar,
      supplier_type: def.supplier_type,
      country_code: def.country_code ?? '',
      supplier_category_id: def.supplier_category_id ?? '',
      is_mandatory: def.is_mandatory,
      default_validity_days: def.default_validity_days ?? '',
    })),
  }
}

export function validateEligibilitySetForm(
  form: EligibilitySetFormState,
  options: { isCreate: boolean },
): EligibilitySetFieldErrors {
  const errors: EligibilitySetFieldErrors = { defRows: {} }

  if (options.isCreate) {
    const key = form.config_key.trim()
    if (!key) errors.config_key = 'required'
    else if (key.length > 64 || !CONFIG_KEY_PATTERN.test(key)) errors.config_key = 'configKey'
  }
  if (!form.name.trim()) {
    errors.name = 'required'
  }
  if (form.defs.length === 0) {
    errors.defs = 'defsRequired'
  }

  const seen = new Set<string>()
  form.defs.forEach((row, index) => {
    const rowErrors: EligibilityDefFieldErrors = {}
    const key = row.requirement_key.trim()
    if (!key) rowErrors.requirement_key = 'required'
    else if (key.length > 64 || !REQUIREMENT_KEY_PATTERN.test(key)) {
      rowErrors.requirement_key = 'requirementKey'
    } else {
      const signature = `${key}|${row.supplier_type}|${row.country_code.trim().toUpperCase()}|${row.supplier_category_id}`
      if (seen.has(signature)) rowErrors.requirement_key = 'duplicateDef'
      seen.add(signature)
    }
    if (!row.label_ar.trim()) rowErrors.label_ar = 'required'
    const country = row.country_code.trim()
    if (country && !/^[A-Za-z]{2}$/.test(country)) rowErrors.country_code = 'countryCode'
    if (row.default_validity_days !== '') {
      const days = Number(row.default_validity_days)
      if (!Number.isInteger(days) || days < 1 || days > 3650) {
        rowErrors.default_validity_days = 'validityDays'
      }
    }
    if (Object.keys(rowErrors).length > 0) errors.defRows[index] = rowErrors
  })

  return errors
}

export function hasEligibilitySetErrors(errors: EligibilitySetFieldErrors): boolean {
  return Boolean(
    errors.config_key || errors.name || errors.defs || Object.keys(errors.defRows).length > 0,
  )
}

export function buildDefPayloads(rows: EligibilityDefFormRow[]): EligibilityRequirementDefPayload[] {
  return rows.map((row) => ({
    requirement_key: row.requirement_key.trim(),
    label_ar: row.label_ar.trim(),
    supplier_type: row.supplier_type,
    country_code: row.country_code.trim() ? row.country_code.trim().toUpperCase() : null,
    supplier_category_id: row.supplier_category_id === '' ? null : Number(row.supplier_category_id),
    is_mandatory: row.is_mandatory,
    default_validity_days:
      row.default_validity_days === '' ? null : Number(row.default_validity_days),
  }))
}

/* ------------------------------------------------------------------ */
/* Document records                                                    */
/* ------------------------------------------------------------------ */

export interface DocumentRecordFormState {
  requirement_def_id: number | ''
  document_id: number | ''
  expires_at: string
}

export interface DocumentRecordFieldErrors {
  requirement_def_id?: string
  document_id?: string
  expires_at?: string
}

const DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/

export function isValidCalendarDate(value: string): boolean {
  if (!DATE_PATTERN.test(value)) return false
  const parsed = new Date(`${value}T00:00:00Z`)
  return !Number.isNaN(parsed.getTime()) && parsed.toISOString().slice(0, 10) === value
}

export function validateDocumentRecordForm(
  form: DocumentRecordFormState,
  options: { requireDef: boolean },
): DocumentRecordFieldErrors {
  const errors: DocumentRecordFieldErrors = {}
  if (options.requireDef && form.requirement_def_id === '') errors.requirement_def_id = 'required'
  if (form.document_id === '' || !Number.isInteger(Number(form.document_id)) || Number(form.document_id) < 1) {
    errors.document_id = 'documentId'
  }
  if (form.expires_at.trim() && !isValidCalendarDate(form.expires_at.trim())) {
    errors.expires_at = 'date'
  }
  return errors
}

/* ------------------------------------------------------------------ */
/* Checklist                                                           */
/* ------------------------------------------------------------------ */

/**
 * UX-only applicability narrowing so a record can be linked to the right def id.
 * The server remains the source of truth for eligibility (API.md §12.3).
 */
export function applicableDefsForSupplier(
  defs: EligibilityRequirementDef[],
  supplier: Pick<Supplier, 'supplier_type' | 'country_code' | 'categories'>,
  applicableKeys: string[],
): EligibilityRequirementDef[] {
  const keys = new Set(applicableKeys)
  const categoryIds = new Set((supplier.categories ?? []).map((c) => c.id))
  const country = supplier.country_code?.toUpperCase()
  return defs.filter((def) => {
    if (!keys.has(def.requirement_key)) return false
    if (def.supplier_type !== supplier.supplier_type) return false
    if (def.country_code && def.country_code.toUpperCase() !== country) return false
    if (def.supplier_category_id != null && !categoryIds.has(def.supplier_category_id)) return false
    return true
  })
}

export interface ChecklistRow {
  requirementKey: string
  def: EligibilityRequirementDef | null
  label: string
  isMandatory: boolean
  /** Newest non-superseded record for the requirement. */
  current: SupplierDocumentRecord | null
  /** Superseded records, newest first. */
  history: SupplierDocumentRecord[]
  /** Reasons returned by the server for this requirement. */
  reasons: EligibilityReason[]
}

export function buildChecklist(
  defs: EligibilityRequirementDef[],
  records: SupplierDocumentRecord[],
  eligibility: Pick<SupplierEligibility, 'reasons' | 'applicable_requirement_keys'> | null | undefined,
): ChecklistRow[] {
  const byKey = new Map<string, EligibilityRequirementDef>()
  defs.forEach((def) => {
    if (!byKey.has(def.requirement_key)) byKey.set(def.requirement_key, def)
  })

  const keys = new Set<string>([
    ...(eligibility?.applicable_requirement_keys ?? []),
    ...byKey.keys(),
  ])

  const sortedRecords = [...records].sort((a, b) => b.id - a.id)

  return [...keys].sort().map((key) => {
    const def = byKey.get(key) ?? null
    const forKey = sortedRecords.filter(
      (record) =>
        record.requirement_key === key ||
        (record.requirement_key == null && def?.id != null && record.requirement_def_id === def.id),
    )
    return {
      requirementKey: key,
      def,
      label: def?.label_ar ?? key,
      isMandatory: def?.is_mandatory ?? true,
      current: forKey.find((record) => record.verification_status !== 'superseded') ?? null,
      history: forKey.filter((record) => record.verification_status === 'superseded'),
      reasons: (eligibility?.reasons ?? []).filter((reason) => reason.requirement_key === key),
    }
  })
}

/** Config-level reasons (no requirement_key), e.g. config missing / draft. */
export function configLevelReasons(reasons: EligibilityReason[] | undefined): EligibilityReason[] {
  return (reasons ?? []).filter((reason) => !reason.requirement_key)
}
