import { apiGet, apiPatch, apiPost, apiPut } from '@/shared/api/http'

import type {
  CreateEmployeePayload,
  Employee,
  EmployeesListMeta,
  ListEmployeesParams,
  UpdateEmployeePayload,
} from '../types/employees'

function toQuery(params: ListEmployeesParams): string {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') {
      return
    }
    query.set(key, String(value))
  })
  const qs = query.toString()
  return qs ? `?${qs}` : ''
}

export async function listEmployees(params: ListEmployeesParams = {}): Promise<{
  data: Employee[]
  meta: EmployeesListMeta
}> {
  const response = await apiGet<Employee[]>(`/api/v1/employees${toQuery(params)}`)
  return {
    data: response.data,
    meta: response.meta as unknown as EmployeesListMeta,
  }
}

export async function getEmployee(id: number): Promise<Employee> {
  const response = await apiGet<Employee>(`/api/v1/employees/${id}`)
  return response.data
}

export async function createEmployee(payload: CreateEmployeePayload): Promise<Employee> {
  const response = await apiPost<Employee>('/api/v1/employees', payload)
  return response.data
}

export async function updateEmployee(
  id: number,
  payload: UpdateEmployeePayload,
): Promise<Employee> {
  const response = await apiPatch<Employee>(`/api/v1/employees/${id}`, payload)
  return response.data
}

export async function activateEmployee(id: number): Promise<Employee> {
  const response = await apiPost<Employee>(`/api/v1/employees/${id}/activate`)
  return response.data
}

export async function deactivateEmployee(id: number): Promise<Employee> {
  const response = await apiPost<Employee>(`/api/v1/employees/${id}/deactivate`)
  return response.data
}

export async function assignEmployeeSupervisor(
  id: number,
  supervisorId: number | null,
): Promise<Employee> {
  const response = await apiPut<Employee>(`/api/v1/employees/${id}/supervisor`, {
    supervisor_id: supervisorId,
  })
  return response.data
}

export async function linkEmployeeUser(id: number, userId: number | null): Promise<Employee> {
  const response = await apiPut<Employee>(`/api/v1/employees/${id}/user`, {
    user_id: userId,
  })
  return response.data
}
