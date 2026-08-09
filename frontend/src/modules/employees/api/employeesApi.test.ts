import { beforeEach, describe, expect, it, vi } from 'vitest'

import * as employeesApi from './employeesApi'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPut: vi.fn(),
  apiPatch: vi.fn(),
}))

import { apiGet, apiPatch, apiPost, apiPut } from '@/shared/api/http'

describe('employeesApi', () => {
  beforeEach(() => {
    vi.mocked(apiGet).mockReset()
    vi.mocked(apiPost).mockReset()
    vi.mocked(apiPut).mockReset()
    vi.mocked(apiPatch).mockReset()
  })

  it('lists employees with filters as query string', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: [
        {
          id: 1,
          employee_number: 'EMP-000001',
          full_name: 'أحمد',
          phone: null,
          email: null,
          status: 'active',
          hire_date: null,
          notes: null,
          organization_unit: { id: 10, name: 'عمليات', code: 'OPS' },
          position: null,
          supervisor: null,
          user: null,
          created_at: null,
          updated_at: null,
        },
      ],
      meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 },
    })

    const result = await employeesApi.listEmployees({
      search: 'أحمد',
      status: 'active',
      organization_unit_id: 10,
      position_id: 3,
      supervisor_id: 2,
    })

    expect(result.data).toHaveLength(1)
    expect(result.data[0]?.full_name).toBe('أحمد')
    expect(apiGet).toHaveBeenCalledWith(
      '/api/v1/employees?search=%D8%A3%D8%AD%D9%85%D8%AF&status=active&organization_unit_id=10&position_id=3&supervisor_id=2',
    )
  })

  it('creates, updates, activates and deactivates employees', async () => {
    vi.mocked(apiPost).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 1, full_name: 'New', employee_number: 'EMP-000002' },
    })
    vi.mocked(apiPatch).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 1, full_name: 'Updated' },
    })

    await employeesApi.createEmployee({
      full_name: 'New',
      organization_unit_id: 1,
    })
    await employeesApi.updateEmployee(1, { full_name: 'Updated' })
    await employeesApi.activateEmployee(1)
    await employeesApi.deactivateEmployee(1)

    expect(apiPost).toHaveBeenCalledWith('/api/v1/employees', {
      full_name: 'New',
      organization_unit_id: 1,
    })
    expect(apiPatch).toHaveBeenCalledWith('/api/v1/employees/1', { full_name: 'Updated' })
    expect(apiPost).toHaveBeenCalledWith('/api/v1/employees/1/activate')
    expect(apiPost).toHaveBeenCalledWith('/api/v1/employees/1/deactivate')
  })

  it('assigns supervisor and links user', async () => {
    vi.mocked(apiPut).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 1 },
    })

    await employeesApi.assignEmployeeSupervisor(1, 5)
    await employeesApi.linkEmployeeUser(1, null)

    expect(apiPut).toHaveBeenCalledWith('/api/v1/employees/1/supervisor', {
      supervisor_id: 5,
    })
    expect(apiPut).toHaveBeenCalledWith('/api/v1/employees/1/user', { user_id: null })
  })
})
