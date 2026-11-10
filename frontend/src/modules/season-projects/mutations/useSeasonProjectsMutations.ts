import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  activateSeasonProject,
  approveSeasonProjectProgressBaseline,
  cancelSeasonProject,
  completeSeasonProject,
  createSeasonProject,
  createSeasonProjectPhase,
  deleteSeasonProject,
  deleteSeasonProjectPhase,
  linkSeasonProjectPurchaseOrder,
  reassignSeasonProjectOwner,
  unlinkSeasonProjectPurchaseOrder,
  updateSeasonProject,
  updateSeasonProjectBudget,
  updateSeasonProjectPhase,
  upsertSeasonProjectProgressBaselineDraft,
} from '../api/seasonProjectsApi'
import type { SeasonProjectProgressCheckpoint } from '../types/seasonProjects'
import { seasonProjectsQueryKey } from '../queries/useSeasonProjectsQuery'
import type { SeasonProjectPhaseFormState } from '../types/seasonProjects'

async function invalidateProject(
  client: ReturnType<typeof useQueryClient>,
  projectId?: number,
): Promise<void> {
  await client.invalidateQueries({ queryKey: seasonProjectsQueryKey })
  if (projectId) {
    await client.invalidateQueries({
      queryKey: [...seasonProjectsQueryKey, 'detail', projectId],
    })
    await client.invalidateQueries({
      queryKey: [...seasonProjectsQueryKey, 'phases', projectId],
    })
    await client.invalidateQueries({
      queryKey: [...seasonProjectsQueryKey, 'budget', projectId],
    })
    await client.invalidateQueries({
      queryKey: [...seasonProjectsQueryKey, 'progress-baselines', projectId],
    })
  }
}

export function useCreateSeasonProjectMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      payload,
      seasonId,
    }: {
      payload: { title: string; description?: string | null; owner_employee_id?: number | null }
      seasonId?: number | null
    }) => createSeasonProject(payload, seasonId),
    onSuccess: () => invalidateProject(client),
  })
}

export function useUpdateSeasonProjectMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      payload,
      seasonId,
    }: {
      id: number
      payload: {
        title?: string
        description?: string | null
        owner_employee_id?: number | null
      }
      seasonId?: number | null
    }) => updateSeasonProject(id, payload, seasonId),
    onSuccess: (row) => invalidateProject(client, row.id),
  })
}

export function useDeleteSeasonProjectMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, seasonId }: { id: number; seasonId?: number | null }) =>
      deleteSeasonProject(id, seasonId),
    onSuccess: () => invalidateProject(client),
  })
}

export function useActivateSeasonProjectMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, seasonId }: { id: number; seasonId?: number | null }) =>
      activateSeasonProject(id, seasonId),
    onSuccess: (row) => invalidateProject(client, row.id),
  })
}

export function useCompleteSeasonProjectMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, seasonId }: { id: number; seasonId?: number | null }) =>
      completeSeasonProject(id, seasonId),
    onSuccess: (row) => invalidateProject(client, row.id),
  })
}

export function useCancelSeasonProjectMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, seasonId }: { id: number; seasonId?: number | null }) =>
      cancelSeasonProject(id, seasonId),
    onSuccess: (row) => invalidateProject(client, row.id),
  })
}

export function useReassignSeasonProjectOwnerMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      ownerEmployeeId,
      seasonId,
    }: {
      id: number
      ownerEmployeeId: number
      seasonId?: number | null
    }) => reassignSeasonProjectOwner(id, ownerEmployeeId, seasonId),
    onSuccess: (row) => invalidateProject(client, row.id),
  })
}

export function useCreateSeasonProjectPhaseMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      projectId,
      payload,
      seasonId,
    }: {
      projectId: number
      payload: Partial<SeasonProjectPhaseFormState> & { title: string }
      seasonId?: number | null
    }) => createSeasonProjectPhase(projectId, payload, seasonId),
    onSuccess: (row) => invalidateProject(client, row.project_id),
  })
}

export function useUpdateSeasonProjectPhaseMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      projectId,
      phaseId,
      payload,
      seasonId,
    }: {
      projectId: number
      phaseId: number
      payload: Partial<SeasonProjectPhaseFormState>
      seasonId?: number | null
    }) => updateSeasonProjectPhase(projectId, phaseId, payload, seasonId),
    onSuccess: (row) => invalidateProject(client, row.project_id),
  })
}

export function useDeleteSeasonProjectPhaseMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      projectId,
      phaseId,
      seasonId,
    }: {
      projectId: number
      phaseId: number
      seasonId?: number | null
    }) => deleteSeasonProjectPhase(projectId, phaseId, seasonId),
    onSuccess: (_data, vars) => invalidateProject(client, vars.projectId),
  })
}

export function useUpdateSeasonProjectBudgetMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      projectId,
      payload,
      seasonId,
    }: {
      projectId: number
      payload: { budget_amount?: number | string | null; currency_code?: string }
      seasonId?: number | null
    }) => updateSeasonProjectBudget(projectId, payload, seasonId),
    onSuccess: (row) => invalidateProject(client, row.project_id),
  })
}

export function useLinkSeasonProjectPurchaseOrderMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      projectId,
      purchaseOrderId,
      notes,
      seasonId,
    }: {
      projectId: number
      purchaseOrderId: number
      notes?: string | null
      seasonId?: number | null
    }) => linkSeasonProjectPurchaseOrder(projectId, purchaseOrderId, notes, seasonId),
    onSuccess: (row) => invalidateProject(client, row.project_id),
  })
}

export function useUnlinkSeasonProjectPurchaseOrderMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      projectId,
      linkId,
      seasonId,
    }: {
      projectId: number
      linkId: number
      seasonId?: number | null
    }) => unlinkSeasonProjectPurchaseOrder(projectId, linkId, seasonId),
    onSuccess: (row) => invalidateProject(client, row.project_id),
  })
}

export function useUpsertSeasonProjectProgressBaselineDraftMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      projectId,
      checkpoints,
      seasonId,
    }: {
      projectId: number
      checkpoints: Array<
        Pick<SeasonProjectProgressCheckpoint, 'effective_at' | 'planned_progress_percent'>
      >
      seasonId?: number | null
    }) => upsertSeasonProjectProgressBaselineDraft(projectId, checkpoints, seasonId),
    onSuccess: (_row, vars) => invalidateProject(client, vars.projectId),
  })
}

export function useApproveSeasonProjectProgressBaselineMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      projectId,
      baselineId,
      seasonId,
    }: {
      projectId: number
      baselineId: number
      seasonId?: number | null
    }) => approveSeasonProjectProgressBaseline(projectId, baselineId, seasonId),
    onSuccess: (_row, vars) => invalidateProject(client, vars.projectId),
  })
}
