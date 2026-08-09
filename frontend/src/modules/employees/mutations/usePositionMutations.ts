import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  activatePosition,
  createPosition,
  deactivatePosition,
  deletePosition,
  updatePosition,
} from '../api/positionsApi'
import { positionsQueryKey } from '../queries/usePositionsQuery'
import type { CreatePositionPayload, UpdatePositionPayload } from '../types/positions'
import { employeesQueryKey } from '../queries/useEmployeesQuery'

export function useCreatePositionMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreatePositionPayload) => createPosition(payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: positionsQueryKey })
    },
  })
}

export function useUpdatePositionMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdatePositionPayload }) =>
      updatePosition(id, payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: positionsQueryKey })
      await queryClient.invalidateQueries({ queryKey: employeesQueryKey })
    },
  })
}

export function useActivatePositionMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => activatePosition(id),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: positionsQueryKey })
    },
  })
}

export function useDeactivatePositionMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deactivatePosition(id),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: positionsQueryKey })
      await queryClient.invalidateQueries({ queryKey: employeesQueryKey })
    },
  })
}

export function useDeletePositionMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deletePosition(id),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: positionsQueryKey })
    },
  })
}
