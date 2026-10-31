import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  activateSeason,
  closeSeason,
  createSeason,
  reopenSeason,
  updateSeason,
} from '../api/seasonsApi'
import { seasonsQueryKey } from '../queries/useSeasonsQuery'
import type { CreateSeasonPayload, UpdateSeasonPayload } from '../types/seasons'

export function useCreateSeasonMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateSeasonPayload) => createSeason(payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: seasonsQueryKey })
    },
  })
}

export function useUpdateSeasonMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdateSeasonPayload }) =>
      updateSeason(id, payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: seasonsQueryKey })
    },
  })
}

export function useActivateSeasonMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => activateSeason(id),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: seasonsQueryKey })
    },
  })
}

export function useCloseSeasonMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => closeSeason(id),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: seasonsQueryKey })
    },
  })
}

export function useReopenSeasonMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => reopenSeason(id, reason),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: seasonsQueryKey })
    },
  })
}
