import type {
  AssetFormState,
  AssetLifecycleAction,
  AssetStatus,
  AssignCustodyFormState,
  LifecycleReasonFormState,
  ReturnCustodyFormState,
} from '../types/assets'
import type { AssetCategoryFormState } from '../types/categories'

export type AssetsListState = 'loading' | 'error' | 'empty' | 'ready'

export function resolveAssetsListState(input: {
  isLoading: boolean
  isError: boolean
  count: number
}): AssetsListState {
  if (input.isLoading) return 'loading'
  if (input.isError) return 'error'
  if (input.count === 0) return 'empty'
  return 'ready'
}

export function assetStatusBadgeClass(status: AssetStatus | string): string {
  switch (status) {
    case 'available':
      return 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200/70'
    case 'in_use':
      return 'bg-sky-50 text-sky-900 ring-1 ring-sky-200/70'
    case 'maintenance':
      return 'bg-amber-50 text-amber-900 ring-1 ring-amber-200/80'
    case 'damaged':
      return 'bg-orange-50 text-orange-900 ring-1 ring-orange-200/70'
    case 'retired':
      return 'bg-slate-100 text-slate-700 ring-1 ring-slate-200/80'
    case 'lost':
      return 'bg-red-50 text-red-800 ring-1 ring-red-200/70'
    default:
      return 'bg-neutral-100 text-neutral-600 ring-1 ring-neutral-200/80'
  }
}

export function custodyStatusBadgeClass(status: string): string {
  switch (status) {
    case 'active':
      return 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200/70'
    case 'returned':
      return 'bg-slate-100 text-slate-700 ring-1 ring-slate-200/80'
    default:
      return 'bg-neutral-100 text-neutral-600 ring-1 ring-neutral-200/80'
  }
}

export function conditionBadgeClass(condition: string | null | undefined): string {
  switch (condition) {
    case 'good':
      return 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200/70'
    case 'fair':
      return 'bg-amber-50 text-amber-900 ring-1 ring-amber-200/80'
    case 'damaged':
      return 'bg-orange-50 text-orange-900 ring-1 ring-orange-200/70'
    case 'unknown':
    default:
      return 'bg-neutral-100 text-neutral-600 ring-1 ring-neutral-200/80'
  }
}

export interface AssetFieldErrors {
  name?: string
  purchase_value?: string
}

export function validateAssetForm(form: AssetFormState): AssetFieldErrors {
  const errors: AssetFieldErrors = {}
  if (!form.name.trim()) {
    errors.name = 'required'
  }
  if (form.purchase_value.trim() !== '') {
    const value = Number(form.purchase_value)
    if (!Number.isFinite(value) || value < 0) {
      errors.purchase_value = 'invalid'
    }
  }
  return errors
}

export interface CategoryFieldErrors {
  name?: string
}

export function validateAssetCategoryForm(form: AssetCategoryFormState): CategoryFieldErrors {
  const errors: CategoryFieldErrors = {}
  if (!form.name.trim()) {
    errors.name = 'required'
  }
  return errors
}

export interface AssignFieldErrors {
  employee_id?: string
}

export function validateAssignForm(form: AssignCustodyFormState): AssignFieldErrors {
  const errors: AssignFieldErrors = {}
  if (form.employee_id === '' || form.employee_id == null) {
    errors.employee_id = 'required'
  }
  return errors
}

export interface ReturnFieldErrors {
  next_status?: string
}

export function validateReturnForm(form: ReturnCustodyFormState): ReturnFieldErrors {
  const errors: ReturnFieldErrors = {}
  if (!form.next_status) {
    errors.next_status = 'required'
  }
  return errors
}

export interface ReasonFieldErrors {
  reason?: string
}

export function validateLifecycleReasonForm(form: LifecycleReasonFormState): ReasonFieldErrors {
  const errors: ReasonFieldErrors = {}
  if (!form.reason.trim()) {
    errors.reason = 'required'
  }
  return errors
}

export interface AssetLifecycleActionDef {
  action: AssetLifecycleAction
  permission: string
  requiresReason: boolean
}

/**
 * UX-only helper: which lifecycle buttons to offer for a status.
 * Backend remains the authority for transition validity.
 */
export function availableAssetLifecycleActions(
  status: AssetStatus | string,
  options?: { hasActiveCustody?: boolean },
): AssetLifecycleActionDef[] {
  const hasActiveCustody = options?.hasActiveCustody ?? status === 'in_use'
  const actions: AssetLifecycleActionDef[] = []

  if (status === 'available') {
    actions.push(
      { action: 'assign', permission: 'assets.assign', requiresReason: false },
      { action: 'maintenance', permission: 'assets.update', requiresReason: false },
      { action: 'retire', permission: 'assets.retire', requiresReason: true },
      { action: 'declare_lost', permission: 'assets.retire', requiresReason: true },
    )
  }

  if (status === 'in_use' || hasActiveCustody) {
    if (status === 'in_use' || hasActiveCustody) {
      actions.push({ action: 'return', permission: 'assets.return', requiresReason: false })
    }
    if (status === 'in_use') {
      actions.push({ action: 'declare_lost', permission: 'assets.retire', requiresReason: true })
    }
  }

  if (status === 'maintenance' || status === 'damaged') {
    actions.push(
      { action: 'restore', permission: 'assets.update', requiresReason: false },
      { action: 'retire', permission: 'assets.retire', requiresReason: true },
      { action: 'declare_lost', permission: 'assets.retire', requiresReason: true },
    )
  }

  return actions
}

export function canShowCreateAssetCta(permissions: string[]): boolean {
  return permissions.includes('assets.create')
}

export function canShowCategoriesButton(permissions: string[]): boolean {
  return permissions.includes('assets.update')
}

export function canManageCategories(permissions: string[]): boolean {
  return permissions.includes('assets.update')
}

export function canEditAsset(permissions: string[]): boolean {
  return permissions.includes('assets.update')
}

export function canDeleteAsset(permissions: string[]): boolean {
  return permissions.includes('assets.delete')
}

export function canShowAssignAction(
  status: AssetStatus | string,
  permissions: string[],
): boolean {
  return status === 'available' && permissions.includes('assets.assign')
}

export function canShowReturnAction(
  status: AssetStatus | string,
  permissions: string[],
  hasActiveCustody = status === 'in_use',
): boolean {
  return hasActiveCustody && permissions.includes('assets.return')
}

export function filterAssetsListParams(input: {
  search: string
  status: AssetStatus | ''
  category_id: number | ''
  warehouse_id: number | ''
  organization_unit_id: number | ''
  employee_id: number | ''
  page: number
  per_page: number
}): Record<string, string | number> {
  const params: Record<string, string | number> = {
    page: input.page,
    per_page: input.per_page,
  }
  if (input.search.trim()) params.search = input.search.trim()
  if (input.status) params.status = input.status
  if (input.category_id !== '') params.category_id = Number(input.category_id)
  if (input.warehouse_id !== '') params.warehouse_id = Number(input.warehouse_id)
  if (input.organization_unit_id !== '') {
    params.organization_unit_id = Number(input.organization_unit_id)
  }
  if (input.employee_id !== '') params.employee_id = Number(input.employee_id)
  return params
}

export function sortMyCustodies<T extends { status: string; assigned_at: string | null }>(
  custodies: T[],
): T[] {
  return [...custodies].sort((a, b) => {
    const aActive = a.status === 'active' ? 0 : 1
    const bActive = b.status === 'active' ? 0 : 1
    if (aActive !== bActive) return aActive - bActive
    const aTime = a.assigned_at ? Date.parse(a.assigned_at) : 0
    const bTime = b.assigned_at ? Date.parse(b.assigned_at) : 0
    return bTime - aTime
  })
}

export function assetsSidebarItems(can: (permission: string) => boolean): Array<{
  key: string
  to: string
  labelKey: string
}> {
  if (!can('assets.view')) return []
  return [
    { key: 'assets', to: '/app/assets', labelKey: 'nav.assets' },
    { key: 'my-custodies', to: '/app/my-custodies', labelKey: 'nav.myCustodies' },
  ]
}

export function emptyAssetForm(): AssetFormState {
  return {
    name: '',
    description: '',
    category_id: '',
    serial_number: '',
    barcode: '',
    condition: 'good',
    warehouse_id: '',
    organization_unit_id: '',
    purchase_value: '',
    acquisition_date: '',
    notes: '',
  }
}

export function emptyAssignForm(): AssignCustodyFormState {
  return {
    employee_id: '',
    expected_return_at: '',
    condition_at_assignment: 'good',
    assignment_notes: '',
  }
}

export function emptyReturnForm(): ReturnCustodyFormState {
  return {
    next_status: 'available',
    condition_at_return: 'good',
    return_notes: '',
  }
}

export function mapAssetErrorCode(code: string | undefined): string {
  switch (code) {
    case 'ASSET_IN_USE':
    case 'ASSET_NOT_AVAILABLE':
    case 'ASSET_ALREADY_ASSIGNED':
    case 'ASSET_NOT_ASSIGNED':
    case 'ASSET_INVALID_STATUS_TRANSITION':
    case 'ASSET_SERIAL_ALREADY_EXISTS':
    case 'ASSET_BARCODE_ALREADY_EXISTS':
    case 'ASSET_EMPLOYEE_INVALID':
    case 'ASSET_WAREHOUSE_INVALID':
    case 'ASSET_ORGANIZATION_INVALID':
    case 'ASSET_CATEGORY_INVALID':
    case 'ASSET_CATEGORY_IN_USE':
    case 'ASSET_CUSTODY_CONFLICT':
    case 'ASSET_RETIRE_REASON_REQUIRED':
    case 'ASSET_IMMUTABLE':
      return code
    default:
      return 'generic'
  }
}

export function formatMoney(value: string | number | null | undefined): string {
  if (value == null || value === '') return '—'
  return String(value)
}

export function formatDateTime(value: string | null | undefined): string {
  if (!value) return '—'
  try {
    return new Intl.DateTimeFormat('ar-SA', {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(new Date(value))
  } catch {
    return value
  }
}

export function formatDate(value: string | null | undefined): string {
  if (!value) return '—'
  try {
    return new Intl.DateTimeFormat('ar-SA', { dateStyle: 'medium' }).format(new Date(value))
  } catch {
    return value
  }
}
