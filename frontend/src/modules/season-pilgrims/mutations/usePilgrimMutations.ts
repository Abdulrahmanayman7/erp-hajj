import { useMutation, useQueryClient } from '@tanstack/vue-query'

import { createPilgrim, updatePilgrim } from '../api/pilgrimsApi'
import { pilgrimsQueryKey } from '../queries/usePilgrimsQuery'
import type { CreatePilgrimPayload, UpdatePilgrimPayload } from '../types/pilgrims'

export function useCreatePilgrimMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreatePilgrimPayload) => createPilgrim(payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: pilgrimsQueryKey })
    },
  })
}

export function useUpdatePilgrimMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdatePilgrimPayload }) =>
      updatePilgrim(id, payload),
    onSuccess: async (_data, variables) => {
      await queryClient.invalidateQueries({ queryKey: pilgrimsQueryKey })
      await queryClient.invalidateQueries({
        queryKey: [...pilgrimsQueryKey, 'detail', variables.id],
      })
    },
  })
}
