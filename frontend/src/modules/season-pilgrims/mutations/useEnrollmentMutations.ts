import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  activateSeasonPilgrim,
  assignSeasonPilgrimGroup,
  cancelSeasonPilgrim,
  changeSeasonPilgrimProgram,
  completeSeasonPilgrim,
  deleteSeasonPilgrim,
  registerSeasonPilgrim,
  setSeasonPilgrimFamilyGroup,
} from '../api/seasonPilgrimsApi'
import { seasonPilgrimsQueryKey } from '../queries/usePilgrimsQuery'
import { familyGroupsQueryKey } from '../queries/useSeasonOpsQueries'
import type { RegisterSeasonPilgrimPayload } from '../types/enrollment'

async function invalidateParticipation(queryClient: ReturnType<typeof useQueryClient>) {
  await queryClient.invalidateQueries({ queryKey: seasonPilgrimsQueryKey })
  await queryClient.invalidateQueries({ queryKey: familyGroupsQueryKey })
}

export function useRegisterSeasonPilgrimMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      payload,
      seasonId,
    }: {
      payload: RegisterSeasonPilgrimPayload
      seasonId?: number | null
    }) => registerSeasonPilgrim(payload, seasonId),
    onSuccess: async () => {
      await invalidateParticipation(queryClient)
    },
  })
}

export function useChangeProgramMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      seasonProgramId,
      reason,
      seasonId,
    }: {
      id: number
      seasonProgramId: number | null
      reason?: string | null
      seasonId?: number | null
    }) => changeSeasonPilgrimProgram(id, seasonProgramId, reason, seasonId),
    onSuccess: async () => {
      await invalidateParticipation(queryClient)
    },
  })
}

export function useAssignPilgrimGroupMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      seasonPilgrimGroupId,
      seasonId,
    }: {
      id: number
      seasonPilgrimGroupId: number | null
      seasonId?: number | null
    }) => assignSeasonPilgrimGroup(id, seasonPilgrimGroupId, seasonId),
    onSuccess: async () => {
      await invalidateParticipation(queryClient)
    },
  })
}

export function useSetFamilyGroupMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      seasonFamilyGroupId,
      seasonId,
    }: {
      id: number
      seasonFamilyGroupId: number | null
      seasonId?: number | null
    }) => setSeasonPilgrimFamilyGroup(id, seasonFamilyGroupId, seasonId),
    onSuccess: async () => {
      await invalidateParticipation(queryClient)
    },
  })
}

export function useActivateSeasonPilgrimMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, seasonId }: { id: number; seasonId?: number | null }) =>
      activateSeasonPilgrim(id, seasonId),
    onSuccess: async () => {
      await invalidateParticipation(queryClient)
    },
  })
}

export function useCompleteSeasonPilgrimMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, seasonId }: { id: number; seasonId?: number | null }) =>
      completeSeasonPilgrim(id, seasonId),
    onSuccess: async () => {
      await invalidateParticipation(queryClient)
    },
  })
}

export function useCancelSeasonPilgrimMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      reason,
      seasonId,
    }: {
      id: number
      reason: string
      seasonId?: number | null
    }) => cancelSeasonPilgrim(id, reason, seasonId),
    onSuccess: async () => {
      await invalidateParticipation(queryClient)
    },
  })
}

export function useDeleteSeasonPilgrimMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, seasonId }: { id: number; seasonId?: number | null }) =>
      deleteSeasonPilgrim(id, seasonId),
    onSuccess: async () => {
      await invalidateParticipation(queryClient)
    },
  })
}
