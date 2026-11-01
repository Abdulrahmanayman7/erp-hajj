import type { CategoryFormState } from '../types/categories'
import type {
  SupplierFormState,
  SupplierLifecycleActionDef,
  SupplierStatus,
  SupplierType,
  SupplierWarning,
} from '../types/suppliers'
import { mapEligibilityErrorCode } from './eligibilityValidation'

export interface SupplierFieldErrors {
  display_name?: string
  supplier_type?: string
  country_code?: string
}

export interface CategoryFieldErrors {
  name?: string
}

export function validateSupplierForm(form: SupplierFormState): SupplierFieldErrors {
  const errors: SupplierFieldErrors = {}
  if (!form.display_name.trim()) {
    errors.display_name = 'required'
  }
  if (!form.supplier_type) {
    errors.supplier_type = 'required'
  }
  if (!form.country_code.trim() || form.country_code.trim().length !== 2) {
    errors.country_code = 'countryCode'
  }
  return errors
}

export function validateCategoryForm(form: CategoryFormState): CategoryFieldErrors {
  const errors: CategoryFieldErrors = {}
  if (!form.name.trim()) {
    errors.name = 'required'
  }
  return errors
}

export function mapSupplierErrorCode(code: string | undefined): string {
  switch (code) {
    case 'SUPPLIER_INVALID_TRANSITION':
    case 'SUPPLIER_NOT_ELIGIBLE':
    case 'SUPPLIER_NUMBER_TAKEN':
    case 'SUPPLIER_LEGAL_ID_DUPLICATE':
    case 'SUPPLIER_CATEGORY_IN_USE':
    case 'SUPPLIER_CATEGORY_NAME_TAKEN':
    case 'SUPPLIER_CATEGORY_CODE_TAKEN':
    case 'SUPPLIER_CATEGORY_CODE_IMMUTABLE':
    case 'SUPPLIER_CATEGORY_INVALID':
    case 'SUPPLIER_NOT_EDITABLE':
    case 'SUPPLIER_DELETE_FORBIDDEN':
    case 'SUPPLIER_COVERAGE_CATEGORY_NOT_LINKED':
    case 'SUPPLIER_GEO_PLACE_INVALID':
    case 'SUPPLIER_PRIMARY_CONTACT_REQUIRED':
      return code
    default:
      return mapEligibilityErrorCode(code)
  }
}

export type SuppliersListViewState = 'loading' | 'error' | 'empty' | 'ready'

export function resolveSuppliersListState(options: {
  isLoading: boolean
  isError: boolean
  count: number
}): SuppliersListViewState {
  if (options.isLoading) return 'loading'
  if (options.isError) return 'error'
  if (options.count === 0) return 'empty'
  return 'ready'
}

export function supplierStatusBadgeClass(status: SupplierStatus): string {
  switch (status) {
    case 'draft':
      return 'bg-slate-100 text-slate-800 ring-1 ring-inset ring-slate-300/80'
    case 'under_review':
      return 'bg-amber-100 text-amber-950 ring-1 ring-inset ring-amber-300/80'
    case 'active':
      return 'bg-emerald-100 text-emerald-950 ring-1 ring-inset ring-emerald-300/80'
    case 'temporarily_suspended':
      return 'bg-orange-100 text-orange-950 ring-1 ring-inset ring-orange-300/80'
    case 'blocked':
      return 'bg-red-100 text-red-950 ring-1 ring-inset ring-red-300/80'
    case 'archived':
      return 'bg-slate-200/80 text-slate-800 ring-1 ring-inset ring-slate-400/50'
    default:
      return 'bg-neutral-100 text-neutral-700 ring-1 ring-inset ring-neutral-300/80'
  }
}

export function supplierStatusDotClass(status: SupplierStatus): string {
  switch (status) {
    case 'draft':
      return 'bg-slate-500'
    case 'under_review':
      return 'bg-amber-500'
    case 'active':
      return 'bg-emerald-500'
    case 'temporarily_suspended':
      return 'bg-orange-500'
    case 'blocked':
      return 'bg-red-500'
    case 'archived':
      return 'bg-slate-600'
    default:
      return 'bg-neutral-400'
  }
}

export const SUPPLIER_STATUSES: SupplierStatus[] = [
  'draft',
  'under_review',
  'active',
  'temporarily_suspended',
  'blocked',
  'archived',
]

export const SUPPLIER_TYPES: SupplierType[] = [
  'company',
  'establishment',
  'individual',
  'government_nonprofit',
  'international',
]

/**
 * UX-only lifecycle actions. Backend validates transitions; activation requires Phase II eligibility.
 */
export function availableSupplierLifecycleActions(status: SupplierStatus): SupplierLifecycleActionDef[] {
  switch (status) {
    case 'draft':
      return [
        { to: 'under_review', permission: 'suppliers.transition', requiresReason: false },
        { to: 'archived', permission: 'suppliers.transition', requiresReason: true, variant: 'danger' },
      ]
    case 'under_review':
      return [
        { to: 'activate', permission: 'suppliers.transition', requiresReason: false, variant: 'primary' },
        { to: 'draft', permission: 'suppliers.transition', requiresReason: true },
        { to: 'blocked', permission: 'suppliers.transition', requiresReason: true, variant: 'warning' },
        { to: 'archived', permission: 'suppliers.transition', requiresReason: true, variant: 'danger' },
      ]
    case 'blocked':
      return [
        { to: 'under_review', permission: 'suppliers.transition', requiresReason: true },
        { to: 'archived', permission: 'suppliers.transition', requiresReason: true, variant: 'danger' },
      ]
    case 'temporarily_suspended':
      return [
        { to: 'blocked', permission: 'suppliers.transition', requiresReason: true, variant: 'warning' },
        { to: 'archived', permission: 'suppliers.transition', requiresReason: true, variant: 'danger' },
      ]
    case 'active':
      return [
        { to: 'temporarily_suspended', permission: 'suppliers.transition', requiresReason: true, variant: 'warning' },
        { to: 'blocked', permission: 'suppliers.transition', requiresReason: true, variant: 'warning' },
        { to: 'archived', permission: 'suppliers.transition', requiresReason: true, variant: 'danger' },
      ]
    default:
      return []
  }
}

export function formatSupplierWarnings(warnings: SupplierWarning[]): string {
  return warnings.map((w) => w.message).join(' · ')
}
