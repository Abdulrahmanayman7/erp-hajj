import type { SupplierCategory } from './categories'

export type SupplierStatus =
  | 'draft'
  | 'under_review'
  | 'active'
  | 'temporarily_suspended'
  | 'blocked'
  | 'archived'

export type SupplierType =
  | 'company'
  | 'establishment'
  | 'individual'
  | 'government_nonprofit'
  | 'international'

export interface SupplierUserRef {
  id: number
  name: string
}

export interface SupplierTransition {
  id: number
  from_status: string | null
  to_status: string
  reason: string | null
  actor: SupplierUserRef | null
  created_at: string | null
}

export interface SupplierContact {
  id: number
  name: string
  role_title: string | null
  email: string | null
  phone: string | null
  is_primary_contact: boolean
  created_at: string | null
  updated_at: string | null
}

export interface GeoPlace {
  id: number
  country_code: string
  kind: string
  code: string
  name_ar: string
  name_en: string | null
  parent_id: number | null
  is_active: boolean
}

export interface SupplierCoverage {
  id: number
  supplier_category_id: number
  geo_place_id: number
  category?: { id: number; name: string } | null
  geo_place?: GeoPlace | null
  created_at: string | null
}

export interface Supplier {
  id: number
  supplier_number: string
  status: SupplierStatus
  supplier_type: SupplierType
  display_name: string
  legal_name: string | null
  country_code: string
  legal_id_type: string | null
  legal_id_value: string | null
  notes: string | null
  activated_at: string | null
  categories: SupplierCategory[]
  created_by?: SupplierUserRef | null
  created_at: string | null
  updated_at: string | null
  transitions?: SupplierTransition[]
  contacts?: SupplierContact[]
  coverages?: SupplierCoverage[]
}

export interface SuppliersListMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ListSuppliersParams {
  q?: string
  status?: SupplierStatus
  category_id?: number | ''
  supplier_type?: SupplierType
  page?: number
  per_page?: number
}

export interface CreateSupplierPayload {
  display_name: string
  legal_name?: string | null
  supplier_type: SupplierType
  country_code: string
  legal_id_type?: string | null
  legal_id_value?: string | null
  notes?: string | null
  category_ids?: number[]
}

export type UpdateSupplierPayload = Partial<CreateSupplierPayload>

export interface SupplierTransitionPayload {
  to: SupplierStatus
  reason?: string | null
}

export interface CreateSupplierContactPayload {
  name: string
  role_title?: string | null
  email?: string | null
  phone?: string | null
  is_primary_contact?: boolean
}

export type UpdateSupplierContactPayload = Partial<CreateSupplierContactPayload>

export interface AddSupplierCoveragePayload {
  supplier_category_id: number
  geo_place_id: number
}

export interface SupplierWarning {
  code: string
  message: string
  supplier_id?: number
  supplier_number?: string
  display_name?: string
}

export interface SupplierMutationResult {
  supplier: Supplier
  warnings: SupplierWarning[]
}

export interface SupplierFormState {
  display_name: string
  legal_name: string
  supplier_type: SupplierType
  country_code: string
  legal_id_type: string
  legal_id_value: string
  notes: string
  category_ids: number[]
}

export type SupplierLifecycleTarget = SupplierStatus | 'activate'

export interface SupplierLifecycleActionDef {
  to: SupplierLifecycleTarget
  permission: string
  requiresReason: boolean
  variant?: 'primary' | 'warning' | 'danger'
}
