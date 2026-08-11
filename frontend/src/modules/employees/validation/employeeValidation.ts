import type { EmployeeFormState } from '../types/employees'
import type { PositionFormState } from '../types/positions'

export interface EmployeeFieldErrors {
  full_name?: string
  organization_unit_id?: string
  email?: string
  phone?: string
}

export interface PositionFieldErrors {
  name?: string
}

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

export function validateEmployeeForm(form: EmployeeFormState): EmployeeFieldErrors {
  const errors: EmployeeFieldErrors = {}

  if (!form.full_name.trim()) {
    errors.full_name = 'required'
  }

  if (form.organization_unit_id === '' || form.organization_unit_id == null) {
    errors.organization_unit_id = 'required'
  }

  const email = form.email.trim()
  if (email && !EMAIL_RE.test(email)) {
    errors.email = 'email'
  }

  return errors
}

export function validatePositionForm(form: PositionFormState): PositionFieldErrors {
  const errors: PositionFieldErrors = {}
  if (!form.name.trim()) {
    errors.name = 'required'
  }
  return errors
}

/** Stable API error codes → i18n keys under employees.errors.* */
export function mapEmployeeErrorCode(code: string | undefined): string {
  switch (code) {
    case 'EMPLOYEE_SUPERVISOR_CYCLE':
      return 'EMPLOYEE_SUPERVISOR_CYCLE'
    case 'EMPLOYEE_SUPERVISOR_INVALID':
      return 'EMPLOYEE_SUPERVISOR_INVALID'
    case 'EMPLOYEE_USER_INVALID':
      return 'EMPLOYEE_USER_INVALID'
    case 'EMPLOYEE_USER_ALREADY_LINKED':
      return 'EMPLOYEE_USER_ALREADY_LINKED'
    case 'EMPLOYEE_ORGANIZATION_INVALID':
      return 'EMPLOYEE_ORGANIZATION_INVALID'
    case 'EMPLOYEE_POSITION_INVALID':
      return 'EMPLOYEE_POSITION_INVALID'
    case 'EMPLOYEE_INACTIVE':
      return 'EMPLOYEE_INACTIVE'
    case 'POSITION_IN_USE':
      return 'POSITION_IN_USE'
    case 'POSITION_CODE_TAKEN':
      return 'POSITION_CODE_TAKEN'
    case 'POSITION_NAME_TAKEN':
      return 'POSITION_NAME_TAKEN'
    default:
      return 'generic'
  }
}

export type EmployeesListViewState = 'loading' | 'error' | 'empty' | 'ready'

export function resolveEmployeesListState(options: {
  isLoading: boolean
  isError: boolean
  count: number
}): EmployeesListViewState {
  if (options.isLoading) return 'loading'
  if (options.isError) return 'error'
  if (options.count === 0) return 'empty'
  return 'ready'
}

export function canShowCreateEmployeeCta(permissions: string[]): boolean {
  return permissions.includes('employees.create')
}

export function canShowPositionsButton(permissions: string[]): boolean {
  return permissions.includes('positions.view')
}

export function filterSupervisorCandidates<T extends { id: number; status: string }>(
  employees: T[],
  selfId: number,
): T[] {
  return employees.filter((e) => e.id !== selfId && e.status === 'active')
}

export function filterLinkableUsers<
  T extends { id: number; status: string },
>(
  users: T[],
  linkedUserIds: Set<number>,
  currentLinkedUserId: number | null,
): T[] {
  return users.filter((user) => {
    if (user.status !== 'active') return false
    if (currentLinkedUserId != null && user.id === currentLinkedUserId) return true
    return !linkedUserIds.has(user.id)
  })
}
