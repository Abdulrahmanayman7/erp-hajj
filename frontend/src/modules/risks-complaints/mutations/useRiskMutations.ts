import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  approveRiskAssessment,
  assessRisk,
  assignRisk,
  closeRisk,
  createRisk,
  deleteRisk,
  updateRisk,
  upsertRiskTreatment,
} from '../api/risksApi'
import { riskDetailQueryKey, risksQueryKey } from '../queries/useRisksQuery'
import type {
  RiskAssessPayload,
  RiskAssignPayload,
  RiskClosePayload,
  RiskCreatePayload,
  RiskTreatmentPayload,
  RiskUpdatePayload,
} from '../types/risks'

async function invalidate(client: ReturnType<typeof useQueryClient>, id?: number) {
  await client.invalidateQueries({ queryKey: risksQueryKey })
  if (id) await client.invalidateQueries({ queryKey: riskDetailQueryKey(id) })
}

export function useCreateRiskMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      payload,
      seasonId,
    }: {
      payload: RiskCreatePayload
      seasonId?: number | null
    }) => createRisk(payload, seasonId),
    onSuccess: () => invalidate(client),
  })
}

export function useUpdateRiskMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      payload,
      seasonId,
    }: {
      id: number
      payload: RiskUpdatePayload
      seasonId?: number | null
    }) => updateRisk(id, payload, seasonId),
    onSuccess: (_, v) => invalidate(client, v.id),
  })
}

export function useAssessRiskMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      payload,
      seasonId,
    }: {
      id: number
      payload: RiskAssessPayload
      seasonId?: number | null
    }) => assessRisk(id, payload, seasonId),
    onSuccess: (_, v) => invalidate(client, v.id),
  })
}

export function useUpsertRiskTreatmentMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      payload,
      seasonId,
    }: {
      id: number
      payload: RiskTreatmentPayload
      seasonId?: number | null
    }) => upsertRiskTreatment(id, payload, seasonId),
    onSuccess: (_, v) => invalidate(client, v.id),
  })
}

export function useApproveRiskAssessmentMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, seasonId }: { id: number; seasonId?: number | null }) =>
      approveRiskAssessment(id, seasonId),
    onSuccess: (_, v) => invalidate(client, v.id),
  })
}

export function useAssignRiskMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      payload,
      seasonId,
    }: {
      id: number
      payload: RiskAssignPayload
      seasonId?: number | null
    }) => assignRisk(id, payload, seasonId),
    onSuccess: (_, v) => invalidate(client, v.id),
  })
}

export function useCloseRiskMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      payload,
      seasonId,
    }: {
      id: number
      payload: RiskClosePayload
      seasonId?: number | null
    }) => closeRisk(id, payload, seasonId),
    onSuccess: (_, v) => invalidate(client, v.id),
  })
}

export function useDeleteRiskMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, seasonId }: { id: number; seasonId?: number | null }) =>
      deleteRisk(id, seasonId),
    onSuccess: (_, v) => invalidate(client, v.id),
  })
}
