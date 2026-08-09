import { describe, expect, it } from 'vitest'

import { ar } from '@/locales/ar'

import type { EmployeeFormState } from '../types/employees'
import {
  canShowCreateEmployeeCta,
  canShowPositionsButton,
  filterLinkableUsers,
  filterSupervisorCandidates,
  mapEmployeeErrorCode,
  resolveEmployeesListState,
  validateEmployeeForm,
  validatePositionForm,
} from './employeeValidation'

function form(partial: Partial<EmployeeFormState> = {}): EmployeeFormState {
  return {
    full_name: '',
    organization_unit_id: '',
    position_id: '',
    phone: '',
    email: '',
    hire_date: '',
    notes: '',
    ...partial,
  }
}

describe('employees list view states', () => {
  it('resolves loading / empty / error / ready', () => {
    expect(resolveEmployeesListState({ isLoading: true, isError: false, count: 0 })).toBe(
      'loading',
    )
    expect(resolveEmployeesListState({ isLoading: false, isError: true, count: 0 })).toBe('error')
    expect(resolveEmployeesListState({ isLoading: false, isError: false, count: 0 })).toBe('empty')
    expect(resolveEmployeesListState({ isLoading: false, isError: false, count: 3 })).toBe('ready')
  })
})

describe('create drawer validation', () => {
  it('requires full_name and organization_unit_id', () => {
    expect(validateEmployeeForm(form())).toEqual({
      full_name: 'required',
      organization_unit_id: 'required',
    })
  })

  it('validates optional email format', () => {
    expect(
      validateEmployeeForm(
        form({
          full_name: 'أحمد',
          organization_unit_id: 1,
          email: 'bad',
        }),
      ),
    ).toEqual({ email: 'email' })

    expect(
      validateEmployeeForm(
        form({
          full_name: 'أحمد',
          organization_unit_id: 1,
          email: 'a@example.com',
        }),
      ),
    ).toEqual({})
  })
})

describe('permission-aware CTAs', () => {
  it('shows create CTA only with employees.create', () => {
    expect(canShowCreateEmployeeCta(['employees.view'])).toBe(false)
    expect(canShowCreateEmployeeCta(['employees.view', 'employees.create'])).toBe(true)
  })

  it('shows positions button only with positions.view', () => {
    expect(canShowPositionsButton(['employees.view'])).toBe(false)
    expect(canShowPositionsButton(['positions.view'])).toBe(true)
  })
})

describe('deactivate confirmation copy', () => {
  it('explains inactive status, history kept, and no auto-reassign', () => {
    const body = ar.employees.confirmDeactivateBody
    expect(body).toContain('غير نشط')
    expect(body).toMatch(/السجل|التاريخ/)
    expect(body).toContain('لن يُعاد تعيين المرؤوسين تلقائياً')
  })
})

describe('supervisor cycle error message', () => {
  it('maps EMPLOYEE_SUPERVISOR_CYCLE to Arabic copy', () => {
    expect(mapEmployeeErrorCode('EMPLOYEE_SUPERVISOR_CYCLE')).toBe('EMPLOYEE_SUPERVISOR_CYCLE')
    const message = ar.employees.errors.EMPLOYEE_SUPERVISOR_CYCLE
    expect(message).toContain('حلقة إشراف')
    expect(message.length).toBeGreaterThan(20)
  })
})

describe('filters and supervisor/user helpers', () => {
  it('excludes self and inactive from supervisor candidates', () => {
    const candidates = filterSupervisorCandidates(
      [
        { id: 1, status: 'active' },
        { id: 2, status: 'inactive' },
        { id: 3, status: 'active' },
      ],
      1,
    )
    expect(candidates.map((c) => c.id)).toEqual([3])
  })

  it('excludes users already linked to another employee', () => {
    const linked = new Set([10, 20])
    const result = filterLinkableUsers(
      [
        { id: 10, status: 'active' },
        { id: 30, status: 'active' },
        { id: 40, status: 'disabled' },
      ],
      linked,
      null,
    )
    expect(result.map((u) => u.id)).toEqual([30])
  })

  it('keeps currently linked user selectable when editing', () => {
    const linked = new Set([10])
    const result = filterLinkableUsers(
      [{ id: 10, status: 'active' }],
      linked,
      10,
    )
    expect(result.map((u) => u.id)).toEqual([10])
  })
})

describe('positions management basics', () => {
  it('requires position name', () => {
    expect(validatePositionForm({ name: '', code: '' })).toEqual({ name: 'required' })
    expect(validatePositionForm({ name: 'منسق', code: 'FIELD' })).toEqual({})
  })

  it('maps POSITION_IN_USE for delete blocked', () => {
    expect(mapEmployeeErrorCode('POSITION_IN_USE')).toBe('POSITION_IN_USE')
    expect(ar.employees.errors.POSITION_IN_USE).toMatch(/عطّل|تعطيل/)
  })
})

describe('list column contract', () => {
  it('exposes required table column labels', () => {
    expect(ar.employees.columns.employeeNumber).toBe('الرقم الوظيفي')
    expect(ar.employees.columns.fullName).toBe('الاسم')
    expect(ar.employees.columns.orgUnit).toBe('الوحدة التنظيمية')
    expect(ar.employees.columns.position).toBe('المسمى الوظيفي')
    expect(ar.employees.columns.supervisor).toBe('المشرف المباشر')
    expect(ar.employees.columns.status).toBe('الحالة')
    expect(ar.employees.columns.actions).toBe('إجراءات')
  })

  it('exposes page header and CTA labels from UI spec', () => {
    expect(ar.employees.title).toBe('الموظفون')
    expect(ar.employees.subtitle).toBe(
      'إدارة سجلات الموظفين والربط بالهيكل التنظيمي والمشرفين',
    )
    expect(ar.employees.add).toBe('إضافة موظف')
    expect(ar.employees.positionsLink).toBe('المسميات الوظيفية')
    expect(ar.nav.employees).toBe('الموظفون')
  })
})
