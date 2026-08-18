import type { CategoryFormState } from '../types/categories'
import type {
  ContractFormState,
  ContractLifecycleAction,
  ContractStatus,
} from '../types/contracts'

export interface ContractFieldErrors {
  title?: string
  contract_category_id?: string
  counterparty_name?: string
  start_date?: string
  end_date?: string
  value?: string
}

export interface CategoryFieldErrors {
  name?: string
}

export function validateContractForm(form: ContractFormState): ContractFieldErrors {
  const errors: ContractFieldErrors = {}

  if (!form.title.trim()) {
    errors.title = 'required'
  }

  if (form.contract_category_id === '' || form.contract_category_id == null) {
    errors.contract_category_id = 'required'
  }

  if (!form.counterparty_name.trim()) {
    errors.counterparty_name = 'required'
  }

  if (!form.start_date) {
    errors.start_date = 'required'
  }

  if (form.start_date && form.end_date && form.end_date < form.start_date) {
    errors.end_date = 'dateRange'
  }

  const value = form.value.trim()
  if (value && Number.isNaN(Number(value))) {
    errors.value = 'numeric'
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

/** Stable API error codes → i18n keys under contracts.errors.* */
export function mapContractErrorCode(code: string | undefined): string {
  switch (code) {
    case 'CONTRACT_INVALID_DATE_RANGE':
    case 'CONTRACT_INVALID_STATUS_TRANSITION':
    case 'CONTRACT_COMMENT_REQUIRED':
    case 'CONTRACT_EMPLOYEE_INVALID':
    case 'CONTRACT_ORGANIZATION_INVALID':
    case 'CONTRACT_CATEGORY_INVALID':
    case 'CONTRACT_NOT_EDITABLE':
    case 'CONTRACT_DELETE_FORBIDDEN':
    case 'CONTRACT_ALREADY_RENEWED':
    case 'CONTRACT_NUMBER_TAKEN':
    case 'CONTRACT_CATEGORY_IN_USE':
    case 'CONTRACT_CATEGORY_CODE_TAKEN':
    case 'CONTRACT_CATEGORY_NAME_TAKEN':
      return code
    default:
      return 'generic'
  }
}

export type ContractsListViewState = 'loading' | 'error' | 'empty' | 'ready'

export function resolveContractsListState(options: {
  isLoading: boolean
  isError: boolean
  count: number
}): ContractsListViewState {
  if (options.isLoading) return 'loading'
  if (options.isError) return 'error'
  if (options.count === 0) return 'empty'
  return 'ready'
}

export function canShowCreateContractCta(permissions: string[]): boolean {
  return permissions.includes('contracts.create')
}

export function canShowCategoriesButton(permissions: string[]): boolean {
  return permissions.includes('contracts.update') || permissions.includes('contracts.view')
}

export function canManageCategories(permissions: string[]): boolean {
  return permissions.includes('contracts.update')
}

export function canEditContract(status: ContractStatus, permissions: string[]): boolean {
  return status === 'draft' && permissions.includes('contracts.update')
}

export interface LifecycleActionDef {
  action: ContractLifecycleAction
  permission: string
  requiresComment: boolean
}

/**
 * UX-only helper: which lifecycle buttons to offer for a status.
 * Backend remains the authority for transition validity.
 */
export function availableLifecycleActions(
  status: ContractStatus,
  options?: { hasRenewalChild?: boolean },
): LifecycleActionDef[] {
  switch (status) {
    case 'draft':
      return [
        { action: 'submit_review', permission: 'contracts.review', requiresComment: false },
        { action: 'cancel', permission: 'contracts.cancel', requiresComment: true },
      ]
    case 'in_review':
      return [
        { action: 'approve', permission: 'contracts.approve', requiresComment: false },
        { action: 'return_draft', permission: 'contracts.review', requiresComment: true },
        { action: 'cancel', permission: 'contracts.cancel', requiresComment: true },
      ]
    case 'approved':
      return [
        { action: 'sign', permission: 'contracts.sign', requiresComment: false },
        { action: 'cancel', permission: 'contracts.cancel', requiresComment: true },
      ]
    case 'signed':
      return [{ action: 'execute', permission: 'contracts.execute', requiresComment: false }]
    case 'executing': {
      const actions: LifecycleActionDef[] = [
        { action: 'close', permission: 'contracts.close', requiresComment: false },
      ]
      if (!options?.hasRenewalChild) {
        actions.push({ action: 'renew', permission: 'contracts.renew', requiresComment: false })
      }
      return actions
    }
    default:
      return []
  }
}

export function contractStatusBadgeClass(status: ContractStatus): string {
  switch (status) {
    case 'draft':
      return 'bg-slate-100 text-slate-800 ring-1 ring-inset ring-slate-300/80'
    case 'in_review':
      return 'bg-amber-100 text-amber-950 ring-1 ring-inset ring-amber-300/80'
    case 'approved':
      return 'bg-sky-100 text-sky-950 ring-1 ring-inset ring-sky-300/80'
    case 'signed':
      return 'bg-violet-100 text-violet-950 ring-1 ring-inset ring-violet-300/80'
    case 'executing':
      return 'bg-emerald-100 text-emerald-950 ring-1 ring-inset ring-emerald-300/80'
    case 'closed':
      return 'bg-slate-200/80 text-slate-800 ring-1 ring-inset ring-slate-400/50'
    case 'renewed':
      return 'bg-teal-100 text-teal-950 ring-1 ring-inset ring-teal-300/80'
    case 'expired':
      return 'bg-orange-100 text-orange-950 ring-1 ring-inset ring-orange-300/80'
    case 'cancelled':
      return 'bg-red-100 text-red-950 ring-1 ring-inset ring-red-300/80'
    default:
      return 'bg-neutral-100 text-neutral-700 ring-1 ring-inset ring-neutral-300/80'
  }
}

export function contractStatusDotClass(status: ContractStatus): string {
  switch (status) {
    case 'draft':
      return 'bg-slate-500'
    case 'in_review':
      return 'bg-amber-500'
    case 'approved':
      return 'bg-sky-500'
    case 'signed':
      return 'bg-violet-500'
    case 'executing':
      return 'bg-emerald-500'
    case 'closed':
      return 'bg-slate-600'
    case 'renewed':
      return 'bg-teal-500'
    case 'expired':
      return 'bg-orange-500'
    case 'cancelled':
      return 'bg-red-500'
    default:
      return 'bg-neutral-400'
  }
}

export const CONTRACT_STATUSES: ContractStatus[] = [
  'draft',
  'in_review',
  'approved',
  'signed',
  'executing',
  'closed',
  'renewed',
  'expired',
  'cancelled',
]
