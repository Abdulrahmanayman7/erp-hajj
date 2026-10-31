import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  createFamilyGroup,
  deleteFamilyGroup,
  setFamilyPrimaryMember,
  updateFamilyGroup,
} from '../api/familyGroupsApi'
import {
  createPilgrimGroup,
  deletePilgrimGroup,
  updatePilgrimGroup,
} from '../api/pilgrimGroupsApi'
import {
  activateSeasonProgram,
  createSeasonProgram,
  deactivateSeasonProgram,
  deleteSeasonProgram,
  updateSeasonProgram,
} from '../api/programsApi'
import {
  familyGroupsQueryKey,
  pilgrimGroupsQueryKey,
  seasonProgramsQueryKey,
} from '../queries/useSeasonOpsQueries'
import type { CreateFamilyGroupPayload, UpdateFamilyGroupPayload } from '../types/families'
import type {
  CreatePilgrimGroupPayload,
  UpdatePilgrimGroupPayload,
} from '../types/pilgrimGroups'
import type { CreateSeasonProgramPayload, UpdateSeasonProgramPayload } from '../types/programs'

export function useCreatePilgrimGroupMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      payload,
      seasonId,
    }: {
      payload: CreatePilgrimGroupPayload
      seasonId?: number | null
    }) => createPilgrimGroup(payload, seasonId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: pilgrimGroupsQueryKey })
    },
  })
}

export function useUpdatePilgrimGroupMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      payload,
      seasonId,
    }: {
      id: number
      payload: UpdatePilgrimGroupPayload
      seasonId?: number | null
    }) => updatePilgrimGroup(id, payload, seasonId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: pilgrimGroupsQueryKey })
    },
  })
}

export function useDeletePilgrimGroupMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, seasonId }: { id: number; seasonId?: number | null }) =>
      deletePilgrimGroup(id, seasonId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: pilgrimGroupsQueryKey })
    },
  })
}

export function useCreateProgramMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      payload,
      seasonId,
    }: {
      payload: CreateSeasonProgramPayload
      seasonId?: number | null
    }) => createSeasonProgram(payload, seasonId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: seasonProgramsQueryKey })
    },
  })
}

export function useUpdateProgramMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      payload,
      seasonId,
    }: {
      id: number
      payload: UpdateSeasonProgramPayload
      seasonId?: number | null
    }) => updateSeasonProgram(id, payload, seasonId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: seasonProgramsQueryKey })
    },
  })
}

export function useActivateProgramMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, seasonId }: { id: number; seasonId?: number | null }) =>
      activateSeasonProgram(id, seasonId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: seasonProgramsQueryKey })
    },
  })
}

export function useDeactivateProgramMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, seasonId }: { id: number; seasonId?: number | null }) =>
      deactivateSeasonProgram(id, seasonId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: seasonProgramsQueryKey })
    },
  })
}

export function useDeleteProgramMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, seasonId }: { id: number; seasonId?: number | null }) =>
      deleteSeasonProgram(id, seasonId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: seasonProgramsQueryKey })
    },
  })
}

export function useCreateFamilyGroupMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      payload,
      seasonId,
    }: {
      payload: CreateFamilyGroupPayload
      seasonId?: number | null
    }) => createFamilyGroup(payload, seasonId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: familyGroupsQueryKey })
    },
  })
}

export function useUpdateFamilyGroupMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      payload,
      seasonId,
    }: {
      id: number
      payload: UpdateFamilyGroupPayload
      seasonId?: number | null
    }) => updateFamilyGroup(id, payload, seasonId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: familyGroupsQueryKey })
    },
  })
}

export function useDeleteFamilyGroupMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, seasonId }: { id: number; seasonId?: number | null }) =>
      deleteFamilyGroup(id, seasonId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: familyGroupsQueryKey })
    },
  })
}

export function useSetFamilyPrimaryMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      familyGroupId,
      seasonPilgrimId,
      seasonId,
    }: {
      familyGroupId: number
      seasonPilgrimId: number
      seasonId?: number | null
    }) => setFamilyPrimaryMember(familyGroupId, seasonPilgrimId, seasonId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: familyGroupsQueryKey })
    },
  })
}
