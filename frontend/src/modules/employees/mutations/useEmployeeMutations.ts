import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  activateEmployee,
  assignEmployeeSupervisor,
  createEmployee,
  deactivateEmployee,
  linkEmployeeUser,
  updateEmployee,
} from '../api/employeesApi'
import { employeesQueryKey } from '../queries/useEmployeesQuery'
import type { CreateEmployeePayload, UpdateEmployeePayload } from '../types/employees'

export function useCreateEmployeeMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateEmployeePayload) => createEmployee(payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: employeesQueryKey })
    },
  })
}

export function useUpdateEmployeeMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdateEmployeePayload }) =>
      updateEmployee(id, payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: employeesQueryKey })
    },
  })
}

export function useActivateEmployeeMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => activateEmployee(id),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: employeesQueryKey })
    },
  })
}

export function useDeactivateEmployeeMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deactivateEmployee(id),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: employeesQueryKey })
    },
  })
}

export function useAssignEmployeeSupervisorMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, supervisorId }: { id: number; supervisorId: number | null }) =>
      assignEmployeeSupervisor(id, supervisorId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: employeesQueryKey })
    },
  })
}

export function useLinkEmployeeUserMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, userId }: { id: number; userId: number | null }) =>
      linkEmployeeUser(id, userId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: employeesQueryKey })
    },
  })
}
