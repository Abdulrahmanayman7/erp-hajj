import type { SupplierType, SupplierUserRef } from './suppliers'

/** Supplier types usable in eligibility requirement defs (API.md §12.1). */
export type EligibilitySupplierType = SupplierType

export type EligibilityConfigStatus = 'draft' | 'approved'

export interface EligibilityRequirementDef {
  id?: number
  requirement_key: string
  label_ar: string
  description?: string | null
  supplier_type: EligibilitySupplierType
  country_code: string | null
  supplier_category_id: number | null
  is_mandatory: boolean
  default_validity_days: number | null
}

export interface EligibilityRequirementSet {
  id: number
  config_key: string
  name: string
  status: EligibilityConfigStatus
  version_no: number
  authored_by: SupplierUserRef | null
  last_edited_by?: SupplierUserRef | null
  approved_by: SupplierUserRef | null
  approved_at: string | null
  requirement_defs?: EligibilityRequirementDef[]
  created_at: string | null
  updated_at: string | null
}

export interface EligibilitySetsListMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ListEligibilitySetsParams {
  config_key?: string
  status?: EligibilityConfigStatus | ''
  page?: number
  per_page?: number
}

export interface EligibilityRequirementDefPayload {
  requirement_key: string
  label_ar: string
  description?: string | null
  supplier_type: EligibilitySupplierType
  country_code?: string | null
  supplier_category_id?: number | null
  is_mandatory?: boolean
  default_validity_days?: number | null
}

export interface CreateEligibilitySetPayload {
  config_key: string
  name: string
  requirement_defs: EligibilityRequirementDefPayload[]
}

export interface UpdateEligibilitySetPayload {
  name?: string
  requirement_defs?: EligibilityRequirementDefPayload[]
}

/** Editable row state of the requirement def form (strings for inputs). */
export interface EligibilityDefFormRow {
  requirement_key: string
  label_ar: string
  supplier_type: EligibilitySupplierType
  country_code: string
  supplier_category_id: number | ''
  is_mandatory: boolean
  default_validity_days: number | ''
}

export interface EligibilitySetFormState {
  config_key: string
  name: string
  defs: EligibilityDefFormRow[]
}

export type DocumentVerificationStatus =
  | 'pending_verification'
  | 'valid'
  | 'rejected'
  | 'expired'
  | 'superseded'

export interface SupplierDocumentRecord {
  id: number
  supplier_id: number
  requirement_def_id: number
  requirement_key: string | null
  document_id: number
  document_number: string | null
  verification_status: DocumentVerificationStatus
  expires_at: string | null
  uploaded_by: SupplierUserRef | null
  verified_by: SupplierUserRef | null
  verified_at: string | null
  rejection_reason: string | null
  supersedes_id: number | null
  created_at: string | null
  updated_at: string | null
}

export interface CreateDocumentRecordPayload {
  requirement_def_id: number
  document_id: number
  expires_at?: string | null
}

export interface SupersedeDocumentRecordPayload {
  document_id: number
  expires_at?: string | null
  requirement_def_id?: number
}

export interface EligibilityReason {
  code: string
  requirement_key?: string
  expires_at?: string
}

export interface EligibilityConfigSnapshot {
  id: number
  config_key: string
  version_no: number
  status: string
}

export interface SupplierEligibility {
  eligible: boolean
  supplier_status: string
  checked_at: string
  reasons: EligibilityReason[]
  applicable_requirement_keys: string[]
  config: EligibilityConfigSnapshot | null
  configs?: EligibilityConfigSnapshot[]
}

export type EligibilityBannerState = 'eligible' | 'ineligible'
